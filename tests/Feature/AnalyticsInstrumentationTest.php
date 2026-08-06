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
            '/reset-password/test-token?email=test@example.com' => 'auth_reset_password',
        ];

        foreach ($screens as $path => $screen) {
            $this->get($path)
                ->assertOk()
                ->assertSee('data-analytics-screen="'.$screen.'"', false);
        }
    }

    public function test_browser_adapter_is_debuggable_and_provider_neutral(): void
    {
        $script = $this->frontendJavaScriptSource();

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
        $script = $this->frontendJavaScriptSource();

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
            'auth_password_reset_started',
            'account_navigation_clicked',
            'account_viewed',
            'access_state',
            'store_product_added',
            'store_checkout_started',
            'store_checkout_validation_failed',
            'store_payment_method_selected',
            'store_payment_started',
            'store_payment_succeeded',
            'store_payment_failed',
            'store_payment_canceled',
            'store_payment_unavailable',
            'store_rsvp_started',
            'store_rsvp_succeeded',
            'store_rsvp_failed',
            'rsvp_confirmed',
            'ticket_purchased',
            'ticket_checked_in',
            'free_event_rsvp_started',
            'free_event_rsvp_succeeded',
            'free_event_rsvp_failed',
            'photo_opened',
            'photo_navigated',
            'photo_saved',
            'photo_shared',
            'photo_deep_link_opened',
            'photo_image_failed',
            'paywall_triggered_from_photo',
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
            'canceled',
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

    public function test_video_reproduction_is_recorded_only_from_youtube_playing_state(): void
    {
        $script = file_get_contents(resource_path('js/features/video-player.js'));

        $this->assertStringContainsString('youtube.PlayerState.PLAYING', $script);
        $this->assertStringContainsString('onStateChange', $script);
        $this->assertStringNotContainsString("iframe.addEventListener('load'", $script);
    }

    public function test_checkout_reuses_the_analytics_session_and_persistence_surfaces_http_failures(): void
    {
        $analytics = file_get_contents(resource_path('js/features/analytics.js'));
        $checkout = file_get_contents(resource_path('js/features/checkout.js'));

        $this->assertStringContainsString('analyticsApi.sessionId = analyticsSessionId', $analytics);
        $this->assertStringContainsString('if (!response.ok)', $analytics);
        $this->assertStringContainsString('[analytics] persistence failed', $analytics);
        $this->assertStringContainsString('analytics_session_id: window.renyAnalytics?.sessionId?.()', $checkout);
    }

    public function test_music_resume_does_not_create_an_extra_reproduction(): void
    {
        $script = file_get_contents(resource_path('js/features/music-player.js'));

        $this->assertStringContainsString('lastTrackedMusicPlaybackRequestId === musicPlaybackRequestId', $script);
        $this->assertStringContainsString('lastTrackedMusicPlaybackRequestId = musicPlaybackRequestId', $script);
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
        $this->assertStringContainsString('schema_version', $taxonomy);
        $this->assertStringContainsString('session_id', $taxonomy);
        $this->assertStringContainsString('event_id', $taxonomy);
        $this->assertStringContainsString('Privacy Contract', $taxonomy);
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
