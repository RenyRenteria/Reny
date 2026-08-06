<?php

namespace Tests\Feature;

use App\Models\EditorialContent;
use App\Models\FanEvent;
use App\Models\Rsvp;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityCmsSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_cms_opens_with_three_sections_live_royal_view_and_new_post_modal(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        EditorialContent::factory()->create([
            'title' => 'Draft ready to manage',
            'created_by_id' => $admin->id,
        ]);
        $this->actingAsAdmin($admin);

        $response = $this->get(route('admin.site-editor.show', ['page' => 'community']));

        $response
            ->assertOk()
            ->assertSeeInOrder(['>Post<', '>Members<', '>RSVP List<'], false)
            ->assertSee('data-community-panel="post"', false)
            ->assertSee('data-community-post-modal-open', false)
            ->assertSee('data-community-post-modal', false)
            ->assertSee('data-community-live-preview', false)
            ->assertSee('src="'.url('/royals').'"', false)
            ->assertSee('Save draft')
            ->assertSee('Schedule')
            ->assertSee('Publish now')
            ->assertSee('Delete post');
    }

    public function test_members_can_be_searched_filtered_and_exported_without_staff_accounts(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'username' => 'staff-account',
        ]);
        $free = User::factory()->create([
            'username' => 'freefan',
            'name' => 'Free Fan',
            'country_code' => 'PA',
            'avatar_path' => 'storage/avatars/freefan.jpg',
            'created_at' => '2025-04-12 10:00:00',
        ]);
        $royal = User::factory()->royal()->create([
            'username' => 'royalfan',
            'name' => 'Royal Fan',
            'country_code' => 'DO',
            'created_at' => '2025-05-20 10:00:00',
        ]);
        User::factory()->expiredRoyal()->create([
            'username' => 'formerroyal',
            'country_code' => 'US',
        ]);
        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', [
            'page' => 'community',
            'community_section' => 'members',
        ]))
            ->assertOk()
            ->assertSee('data-community-panel="members"', false)
            ->assertSeeInOrder(['Plan', 'Photo', 'Username', 'Country', 'A member since'])
            ->assertSee('@'.$free->username)
            ->assertSee('@'.$royal->username)
            ->assertSee('Panama')
            ->assertSee('Dominican Republic')
            ->assertDontSee('@'.$admin->username);

        $this->get(route('admin.site-editor.show', [
            'page' => 'community',
            'community_section' => 'members',
            'member_plan' => 'royal',
            'member_search' => 'royal',
        ]))
            ->assertOk()
            ->assertSee('@'.$royal->username)
            ->assertDontSee('@'.$free->username)
            ->assertDontSee('@formerroyal');

        $csv = $this->get(route('admin.site-editor.community-members.export', [
            'member_plan' => 'royal',
            'member_search' => 'royal',
        ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('plan,photo,username,country,"member since"', $csv);
        $this->assertStringContainsString('"Royal Pass",,royalfan,"Dominican Republic",2025-05-20', $csv);
        $this->assertStringNotContainsString('freefan', $csv);
        $this->assertStringNotContainsString('staff-account', $csv);
    }

    public function test_rsvp_list_unifies_leads_and_issued_tickets_per_concert(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $ticketHolder = User::factory()->create([
            'name' => 'Ticket Fan',
            'email' => 'ticket@example.com',
        ]);
        $event = FanEvent::create([
            'title' => 'Reny Renteria en Concierto',
            'venue' => 'Teatro Nacional',
            'timezone' => 'America/Panama',
            'starts_at' => now()->addMonth(),
            'metadata' => ['store_event_key' => 'concert'],
        ]);
        Rsvp::create([
            'event_key' => 'concert',
            'event_name' => 'Reny Renteria en Concierto',
            'name' => 'Ticket Fan',
            'email' => 'ticket@example.com',
            'country' => 'Panama',
        ]);
        Rsvp::create([
            'event_key' => 'concert',
            'event_name' => 'Reny Renteria en Concierto',
            'name' => 'Lead Fan',
            'email' => 'lead@example.com',
            'country' => 'Mexico',
        ]);
        Rsvp::create([
            'event_key' => 'other-concert',
            'event_name' => 'Other Concert',
            'name' => 'Other Fan',
            'email' => 'other@example.com',
            'country' => 'Colombia',
        ]);

        foreach (range(1, 2) as $index) {
            Ticket::create([
                'user_id' => $ticketHolder->id,
                'event_id' => $event->id,
                'ticket_code_hash' => hash('sha256', 'ticket-'.$index),
                'holder_name' => 'Ticket Fan',
                'status' => 'confirmed',
                'rsvp_status' => 'confirmed',
            ]);
        }

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', [
            'page' => 'community',
            'community_section' => 'rsvp',
            'rsvp_event' => 'concert',
        ]))
            ->assertOk()
            ->assertSee('Reny Renteria en Concierto (3)')
            ->assertSee('Ticket Fan')
            ->assertSee('ticket@example.com')
            ->assertSee('Lead Fan')
            ->assertSee('lead@example.com')
            ->assertSee('<td><strong>2</strong></td>', false)
            ->assertDontSee('Other Fan');

        $csv = $this->get(route('admin.site-editor.community-rsvps.export', ['event' => 'concert']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('name,email,tickets', $csv);
        $this->assertStringContainsString('"Ticket Fan",ticket@example.com,2', $csv);
        $this->assertStringContainsString('"Lead Fan",lead@example.com,1', $csv);
        $this->assertStringNotContainsString('Other Fan', $csv);
    }

    public function test_all_cms_roles_can_open_each_community_section(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_ARTIST_ADMIN, User::ROLE_EDITOR] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAsAdmin($user);

            foreach (['post', 'members', 'rsvp'] as $section) {
                $this->get(route('admin.site-editor.show', [
                    'page' => 'community',
                    'community_section' => $section,
                ]))->assertOk();
            }
        }
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
