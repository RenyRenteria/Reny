<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_navigation_uses_the_approved_items_and_destinations(): void
    {
        $paths = ['/', '/royals', '/videos', '/music', '/music/albums', '/music/singles', '/music/playlists', '/photos', '/shows', '/store', '/merch', '/bio', '/contacto', '/login', '/register', '/forgot-password', '/store/checkout/royal'];
        $expected = ['Shop' => '/store', 'Shows' => '/shows', 'Royal Pass' => '/royals', 'Music' => '/music', 'My Account' => '/account'];

        foreach ($paths as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $dom = new \DOMDocument;
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new \DOMXPath($dom);

            foreach (['Main menu', 'Mobile menu'] as $label) {
                $links = $xpath->query('//nav[@aria-label="'.$label.'"]/a');
                $this->assertCount(count($expected), $links, "Wrong menu on [{$path}]");
                foreach (array_keys($expected) as $index => $text) {
                    $link = $links->item($index);
                    $this->assertSame(url($expected[$text]), $link->getAttribute('href'));
                    $this->assertStringStartsWith($text, trim($link->textContent));
                }
            }
            $this->assertSame(1, substr_count($html, 'class="site-header"'));
            $this->assertSame(1, substr_count($html, 'class="site-footer"'));
            $this->assertStringNotContainsString('mobile-bottom-nav', $html);
            $this->assertStringNotContainsString('class="sidebar"', $html);
            $this->assertSame(1, substr_count($html, 'id="site-content"'));
        }
    }

    public function test_navigation_marks_primary_and_nested_pages_active(): void
    {
        $pages = ['/store' => '/store', '/merch' => '/store', '/store/checkout/royal' => '/store', '/shows' => '/shows', '/royals' => '/royals', '/music' => '/music', '/music/albums' => '/music', '/videos' => '/music', '/login' => '/account'];
        foreach ($pages as $path => $href) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<a href="'.preg_quote(url($href), '/').'"[^>]*aria-current="page"/', $html, "Missing active item on [{$path}]");
        }
    }

    public function test_account_and_points_share_the_public_shell_without_losing_member_state(): void
    {
        $user = User::factory()->royal()->create(['name' => 'Royal Listener']);
        foreach (['/account', '/points'] as $path) {
            $this->actingAs($user)->get($path)->assertOk()
                ->assertSee('class="site-header"', false)
                ->assertSee('class="site-footer"', false)
                ->assertSee('Royal Listener')
                ->assertSee('ROYAL MEMBER');
        }
    }
}
