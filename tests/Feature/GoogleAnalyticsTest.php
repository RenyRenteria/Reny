<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_load_the_configured_tag_once_in_the_head(): void
    {
        config(['analytics.google.enabled' => true]);

        foreach (['/', '/music', '/music/albums', '/music/singles', '/music/playlists', '/videos', '/photos', '/royals', '/community', '/shows', '/merch', '/store', '/bio', '/contacto', '/login', '/register', '/forgot-password', '/session-expired'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $tag = 'https://www.googletagmanager.com/gtag/js?id=G-VZW6QP0YSW';

            $this->assertSame(1, substr_count($html, $tag), $path);
            $this->assertStringContainsString("gtag('config', 'G-VZW6QP0YSW'", $html, $path);
            $this->assertLessThan(strpos($html, '</head>'), strpos($html, $tag), $path);
        }
    }

    public function test_tag_is_disabled_in_tests_and_can_be_disabled_or_reconfigured(): void
    {
        $this->assertFalse(config('analytics.google.enabled'));
        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com/gtag/js');

        config(['analytics.google.enabled' => true, 'analytics.google.measurement_id' => 'G-TEST123456']);
        $this->get('/')->assertOk()->assertSee('gtag/js?id=G-TEST123456')->assertDontSee('G-VZW6QP0YSW');

        config(['analytics.google.enabled' => false]);
        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com/gtag/js');

        config(['analytics.google.enabled' => true, 'analytics.google.measurement_id' => '']);
        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com/gtag/js');
    }

    public function test_reset_links_and_admin_previews_do_not_load_google_analytics(): void
    {
        config(['analytics.google.enabled' => true]);

        $this->get('/reset-password/test-token?email=test@example.com')
            ->assertOk()->assertDontSee('googletagmanager.com/gtag/js');
        $this->get(route('admin.login'))->assertOk()->assertDontSee('googletagmanager.com/gtag/js');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->withSession(['admin_authenticated_at' => now()->timestamp]);

        $this->get(route('admin.site-editor.preview', ['page' => 'home']))
            ->assertOk()->assertDontSee('googletagmanager.com/gtag/js');
    }
}
