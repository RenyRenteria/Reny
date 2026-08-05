<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    public function test_public_navigation_uses_the_requested_items_and_order(): void
    {
        $paths = ['/', '/royals', '/videos', '/music', '/photos', '/shows', '/store'];
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
            '/royals' => '/royals',
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
}
