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
        $this->get('/')
            ->assertOk()
            ->assertSee('VIP Mix')
            ->assertSee('Sign in')
            ->assertSee('Create account')
            ->assertDontSee('Royal-only audio stream');
    }

    public function test_royal_member_sees_full_premium_ui(): void
    {
        $user = User::factory()->royal()->create();

        $this->actingAs($user)
            ->get('/')
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
            ->assertDontSee('secure_stream_url');

        $royalUser = User::factory()->royal()->create();

        $this->actingAs($royalUser)
            ->get('/royal/content/vip-mix')
            ->assertOk()
            ->assertSee('secure_stream_url:royal-only-vip-mix');
    }

    public function test_royal_content_paywall_differs_by_ineligible_state_without_premium_payload(): void
    {
        $states = [
            [User::factory()->create(), 'Royal Pass required', 'Get your Royal Pass', 'open'],
            [User::factory()->expiredRoyal()->create(), 'Reactivate Royal Pass', 'Reactivate Royal Pass', 'royal_expired'],
            [User::factory()->refundedRoyal()->create(), 'Royal Pass was refunded', 'Buy Royal Pass again', 'refunded'],
            [User::factory()->paymentFailedRoyal()->create(), 'Update payment to continue', 'Update payment', 'payment_failed'],
        ];

        foreach ($states as [$user, $title, $action, $state]) {
            $this->actingAs($user)
                ->get('/royal/content/vip-mix')
                ->assertForbidden()
                ->assertSee($title)
                ->assertSee($action)
                ->assertSee('data-access-state="'.$state.'"', false)
                ->assertSee('data-source-route="/royal/content/vip-mix"', false)
                ->assertDontSee('secure_stream_url')
                ->assertDontSee('royal-only-vip-mix');
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
