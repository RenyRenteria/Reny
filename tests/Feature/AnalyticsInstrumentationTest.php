<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsInstrumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_expose_analytics_screen_metadata(): void
    {
        $screens = [
            '/' => 'home',
            '/music' => 'music',
            '/videos' => 'videos',
            '/photos' => 'photos',
            '/community' => 'community',
            '/store' => 'store',
            '/login' => 'auth_login',
            '/register' => 'auth_register',
            '/forgot-password' => 'auth_forgot_password',
        ];

        foreach ($screens as $path => $screen) {
            $this->get($path)
                ->assertOk()
                ->assertSee('data-analytics-screen="'.$screen.'"', false);
        }
    }

    public function test_browser_adapter_is_debuggable_and_provider_neutral(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('window.renyAnalytics', $script);
        $this->assertStringContainsString('reny_analytics_debug', $script);
        $this->assertStringContainsString('analytics_debug', $script);
        $this->assertStringContainsString('window.dataLayer', $script);
        $this->assertStringContainsString('window.gtag', $script);
        $this->assertStringContainsString('window.plausible', $script);
        $this->assertStringContainsString('window.posthog', $script);
        $this->assertStringContainsString('window.mixpanel', $script);
    }

    public function test_project4_core_events_are_instrumented(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        foreach ([
            'page_view',
            'permission_denied',
            'paywall_cta_clicked',
            'music_view_all_clicked',
            'music_play_clicked',
            'music_play_ready',
            'music_play_started',
            'music_play_failed',
            'music_access_blocked',
            'music_permission_cta_clicked',
            'music_deluxe_clicked',
            'video_view_all_clicked',
            'video_play_clicked',
            'video_play_started',
            'video_external_opened',
            'video_play_failed',
            'community_note_opened',
            'community_like_clicked',
            'community_reply_submitted',
            'community_share_clicked',
            'community_poll_voted',
            'community_club_opened',
            'community_club_joined',
            'community_club_created',
            'community_create_club_started',
            'auth_login_started',
            'auth_register_started',
            'auth_password_recovery_started',
            'account_navigation_clicked',
            'account_viewed',
            'access_state',
            'store_product_added',
            'store_checkout_started',
            'store_payment_method_selected',
            'store_payment_succeeded',
            'store_payment_failed',
            'store_rsvp_started',
            'store_rsvp_succeeded',
            'store_rsvp_failed',
            'photo_opened',
        ] as $eventName) {
            $this->assertStringContainsString($eventName, $script);
        }

        foreach ([
            'checkout_state',
            'unavailable',
            'validation_failed',
            'payment_started',
            'payment_success',
            'payment_failed',
            'paypal_not_configured',
            'paypal_sdk_unavailable',
            'missing_name',
            'invalid_email',
            'invalid_phone',
            'missing_country',
        ] as $checkoutState) {
            $this->assertStringContainsString($checkoutState, $script);
        }
    }

    public function test_project4_event_taxonomy_is_documented(): void
    {
        $taxonomyPath = base_path('docs/analytics/project-4-event-taxonomy.md');

        $this->assertFileExists($taxonomyPath);

        $taxonomy = file_get_contents($taxonomyPath);

        $this->assertStringContainsString('window.renyAnalytics.events', $taxonomy);
        $this->assertStringContainsString('screen', $taxonomy);
        $this->assertStringContainsString('item_type', $taxonomy);
        $this->assertStringContainsString('item_id', $taxonomy);
        $this->assertStringContainsString('result', $taxonomy);
    }

    public function test_account_and_denied_pages_expose_access_state_metadata(): void
    {
        $user = User::factory()->paymentFailedRoyal()->create();

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('data-analytics-screen="account"', false)
            ->assertSee('data-access-state="payment_failed"', false);

        $this->actingAs($user)
            ->get('/royal/content/vip-mix')
            ->assertForbidden()
            ->assertSee('data-analytics-screen="permission_denied"', false)
            ->assertSee('data-access-state="payment_failed"', false);
    }
}
