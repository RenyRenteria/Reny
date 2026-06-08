<?php

namespace Tests\Feature;

use Tests\TestCase;

class CommunityPageTest extends TestCase
{
    public function test_community_page_mounts_clean_country_groups_experience(): void
    {
        $response = $this->get('/community');

        $response->assertOk();
        $response->assertSee('Reny Direct Posts');
        $response->assertSee('Polls');
        $response->assertSee('Country Groups');
        $response->assertSee('Create custom country group');
        $response->assertSee('class="tab is-active"', false);
        $response->assertSee('href="' . url('/community') . '"', false);

        $html = $response->getContent();

        $this->assertSame(2, substr_count($html, 'class="direct-post-card"'));
        $this->assertSame(2, substr_count($html, 'class="poll-card"'));
        $this->assertSame(4, substr_count($html, 'class="country-group-tab'));
        $this->assertStringNotContainsString('Main feed is Reny-only', $html);
        $this->assertStringNotContainsString('Users cannot publish directly here', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('youtube', strtolower($html));
    }

    public function test_existing_tabs_link_to_community_route(): void
    {
        foreach (['/', '/videos', '/photos'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="' . url('/community') . '"', false)
                ->assertDontSee('href="#community"', false);
        }
    }
}
