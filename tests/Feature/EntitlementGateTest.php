<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_mode_renders_preview_without_premium_payload(): void
    {
        $this->get('/music')
            ->assertOk()
            ->assertSee('VIP Mix')
            ->assertSee('Get your Royal Pass')
            ->assertDontSee('Royal-only audio stream');
    }

    public function test_royal_member_sees_full_premium_ui(): void
    {
        $user = User::factory()->royal()->create();

        $this->actingAs($user)
            ->get('/music')
            ->assertOk()
            ->assertSee('Royal-only audio stream')
            ->assertDontSee('Open users can see the drop');
    }

    public function test_royal_content_route_requires_authenticated_royal_access(): void
    {
        $this->get('/royal/content/vip-mix')->assertRedirect('/login');

        $openUser = User::factory()->create();

        $this->actingAs($openUser)
            ->get('/royal/content/vip-mix')
            ->assertForbidden()
            ->assertSee('Royal Pass required')
            ->assertSee('Registered')
            ->assertSee('Get your Royal Pass')
            ->assertDontSee('secure_stream_url');

        $royalUser = User::factory()->royal()->create();

        $this->actingAs($royalUser)
            ->get('/royal/content/vip-mix')
            ->assertOk()
            ->assertSee('secure_stream_url:royal-only-vip-mix');
    }

    public function test_non_royal_paid_states_get_reactivation_ui_without_payload(): void
    {
        $states = [
            [User::factory()->expiredRoyal()->create(), 'Royal Expired'],
            [User::factory()->paymentFailedRoyal()->create(), 'Payment Failed'],
            [User::factory()->refundedRoyal()->create(), 'Refunded'],
        ];

        foreach ($states as [$user, $label]) {
            $this->actingAs($user)
                ->get('/royal/content/vip-mix')
                ->assertForbidden()
                ->assertSee($label)
                ->assertSee('Reactivate Royal Pass')
                ->assertDontSee('secure_stream_url');
        }
    }

    public function test_open_community_keeps_previews_but_blocks_interactions(): void
    {
        $this->get('/community')
            ->assertOk()
            ->assertSee('Country Clubs')
            ->assertSee('Poll results stay visible in Open mode.')
            ->assertSee('Club previews stay public')
            ->assertDontSee('Create group')
            ->assertDontSee('Who is going to the first meetup?');
    }
}
