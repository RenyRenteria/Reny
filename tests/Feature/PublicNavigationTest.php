<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_navigation_uses_the_requested_items_and_order(): void
    {
        $paths = ['/', '/community', '/videos', '/music', '/photos', '/shows', '/store'];
        $labels = ['Royals', 'Videos', 'Music', 'Shows', 'Store'];

        foreach ($paths as $path) {
            $html = $this->get($path)
                ->assertOk()
                ->getContent();

            preg_match('/<nav class="tabs" aria-label="Main menu">(.*?)<\/nav>/s', $html, $matches);
            $navHtml = $matches[1] ?? '';

            $this->assertSame(5, substr_count($navHtml, '<a '), "Unexpected desktop nav item count on [{$path}]");
            $this->assertStringNotContainsString('>Photos</span>', $navHtml);
            $this->assertStringNotContainsString('>Community</span>', $navHtml);

            $previousPosition = -1;

            foreach ($labels as $label) {
                $position = strpos($navHtml, ">{$label}</span>");

                $this->assertNotFalse($position, "Missing [{$label}] navigation item on [{$path}]");
                $this->assertGreaterThan($previousPosition, $position, "Navigation order is incorrect on [{$path}]");
                $previousPosition = $position;
            }
        }
    }

    public function test_public_navigation_marks_each_primary_page_active(): void
    {
        $activePages = [
            '/community' => '/community',
            '/videos' => '/videos',
            '/music' => '/music',
            '/shows' => '/shows',
            '/store' => '/store',
        ];

        foreach ($activePages as $path => $activeHref) {
            $html = $this->get($path)
                ->assertOk()
                ->getContent();

            $this->assertMatchesRegularExpression(
                '/class="tab is-active" href="'.preg_quote(url($activeHref), '/').'" aria-current="page"/',
                $html,
                "Missing active navigation state on [{$path}]"
            );
        }
    }

    public function test_mobile_navigation_exposes_sign_in_and_guest_state(): void
    {
        $html = $this->get('/music')
            ->assertOk()
            ->getContent();

        preg_match('/<nav class="mobile-bottom-nav" aria-label="Mobile menu">(.*?)<\/nav>/s', $html, $matches);
        $navHtml = $matches[1] ?? '';

        $this->assertSame(6, substr_count($navHtml, '<a'));
        $this->assertStringContainsString('href="'.route('login').'"', $navHtml);
        $this->assertStringContainsString('aria-label="Sign in — Guest"', $navHtml);
        $this->assertStringContainsString('data-access-state="guest"', $navHtml);
        $this->assertStringContainsString('data-analytics-id="mobile_sign_in"', $navHtml);
    }

    public function test_mobile_navigation_exposes_logged_in_account_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/music')
            ->assertOk()
            ->assertSee('href="'.route('account.show').'"', false)
            ->assertSee('aria-label="Account — Logged in"', false)
            ->assertSee('data-access-state="open"', false)
            ->assertSee('data-analytics-id="mobile_account"', false);
    }

    public function test_mobile_navigation_exposes_royal_account_state(): void
    {
        $user = User::factory()->royal()->create();

        $this->actingAs($user)
            ->get('/music')
            ->assertOk()
            ->assertSee('aria-label="Account — Royal"', false)
            ->assertSee('data-access-state="royal_active"', false);
    }

    public function test_mobile_account_entry_marks_account_pages_active(): void
    {
        $user = User::factory()->create();

        foreach (['/account', '/points'] as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertOk()
                ->assertSee('class="mobile-nav-account account-action is-active"', false)
                ->assertSee('aria-current="page"', false);
        }
    }
}
