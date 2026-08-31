<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    public function test_public_navigation_uses_the_requested_items_and_order(): void
    {
        $paths = ['/', '/royals', '/videos', '/music', '/photos', '/shows', '/store'];
        $labels = ['Videos', 'Music', 'Shows'];

        foreach ($paths as $path) {
            $html = $this->get($path)
                ->assertOk()
                ->getContent();

            foreach (['Main menu' => 'desktop', 'Mobile menu' => 'mobile'] as $ariaLabel => $variant) {
                preg_match('/<nav[^>]+aria-label="'.preg_quote($ariaLabel, '/').'"[^>]*>(.*?)<\/nav>/s', $html, $matches);
                $this->assertArrayHasKey(1, $matches, "Missing {$variant} navigation on [{$path}]");
                $navHtml = $matches[1];

                $this->assertSame(3, substr_count($navHtml, '<a '), "Unexpected {$variant} nav item count on [{$path}]");
                $this->assertStringNotContainsString('href="'.url('/royals').'"', $navHtml);
                $this->assertStringNotContainsString('href="'.url('/store').'"', $navHtml);
                $this->assertStringNotContainsString('>Royals</span>', $navHtml);
                $this->assertStringNotContainsString('>Store</span>', $navHtml);
                $this->assertStringNotContainsString('>Photos</span>', $navHtml);
                $this->assertStringNotContainsString('>Community</span>', $navHtml);

                $previousPosition = -1;

                foreach ($labels as $label) {
                    $position = strpos($navHtml, ">{$label}</span>");

                    $this->assertNotFalse($position, "Missing [{$label}] {$variant} navigation item on [{$path}]");
                    $this->assertGreaterThan($previousPosition, $position, "{$variant} navigation order is incorrect on [{$path}]");
                    $previousPosition = $position;
                }
            }
        }
    }

    public function test_public_navigation_marks_each_primary_page_active(): void
    {
        $activePages = [
            '/videos' => '/videos',
            '/music' => '/music',
            '/shows' => '/shows',
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
