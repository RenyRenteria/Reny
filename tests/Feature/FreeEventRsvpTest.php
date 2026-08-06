<?php

namespace Tests\Feature;

use App\Models\Rsvp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FreeEventRsvpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_guest_can_register_for_visible_free_event(): void
    {
        $this->postJson(route('community.free-event-rsvp.store'), [
            'event_key' => 'concert',
            'event_name' => 'Reny Renteria en Concierto',
            'name' => 'Ana Fan',
            'email' => 'ANA@example.com',
            'country' => 'Panama',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'registered')
            ->assertJsonPath('message', 'Te has registrado con éxito! Te esperamos.');

        $this->assertDatabaseHas('rsvps', [
            'event_key' => 'concert',
            'event_name' => 'Reny Renteria en Concierto',
            'name' => 'Ana Fan',
            'email' => 'ana@example.com',
            'country' => 'Panama',
        ]);
    }

    public function test_duplicate_email_for_same_event_returns_already_registered_message(): void
    {
        $payload = [
            'event_key' => 'concert',
            'event_name' => 'Reny Renteria en Concierto',
            'name' => 'Ana Fan',
            'email' => 'ana@example.com',
            'country' => 'Panama',
        ];

        $this->postJson(route('community.free-event-rsvp.store'), $payload)->assertCreated();

        $this->postJson(route('community.free-event-rsvp.store'), [
            ...$payload,
            'email' => 'ANA@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'already_registered')
            ->assertJsonPath('message', 'Ya estás registrado. Te esperamos!');

        $this->assertDatabaseCount('rsvps', 1);
    }

    public function test_free_event_registration_validates_required_fields_and_email(): void
    {
        $this->postJson(route('community.free-event-rsvp.store'), [
            'event_key' => 'concert',
            'event_name' => 'Reny Renteria en Concierto',
            'name' => '',
            'email' => 'not-an-email',
            'country' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'country']);

        $this->assertDatabaseCount('rsvps', 0);
    }

    public function test_paid_visible_price_is_not_accepted_by_free_event_endpoint(): void
    {
        $this->postJson(route('community.free-event-rsvp.store'), [
            'event_key' => 'listening',
            'event_name' => 'Festival de la Rosa Dorada',
            'name' => 'Paid Fan',
            'email' => 'paid@example.com',
            'country' => 'Panama',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event_key']);

        $this->assertDatabaseCount('rsvps', 0);
    }

    public function test_admin_community_page_lists_and_exports_rsvps_by_event(): void
    {
        Rsvp::create([
            'event_key' => 'concert',
            'event_name' => 'Reny Renteria en Concierto',
            'name' => 'Ana Fan',
            'email' => 'ana@example.com',
            'country' => 'Panama',
        ]);
        Rsvp::create([
            'event_key' => 'listening',
            'event_name' => 'Festival de la Rosa Dorada',
            'name' => 'Paid Fan',
            'email' => 'paid@example.com',
            'country' => 'Mexico',
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', [
            'page' => 'community',
            'rsvp_event' => 'concert',
        ]))
            ->assertOk()
            ->assertSee('RSVP List')
            ->assertSee('Reny Renteria en Concierto (1)')
            ->assertSee('Ana Fan')
            ->assertSee('ana@example.com')
            ->assertSee('<td><strong>1</strong></td>', false)
            ->assertDontSee('Panama')
            ->assertDontSee('Paid Fan');

        $response = $this->get(route('admin.site-editor.community-rsvps.export', ['event' => 'concert']))
            ->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('name,email,tickets', $csv);
        $this->assertStringContainsString('"Ana Fan",ana@example.com,1', $csv);
        $this->assertStringNotContainsString('Paid Fan', $csv);
        $this->assertStringNotContainsString('event_name', $csv);
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
