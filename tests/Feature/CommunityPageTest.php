<?php

namespace Tests\Feature;

use Tests\TestCase;

class CommunityPageTest extends TestCase
{
    public function test_community_page_mounts_approved_mockup_experience(): void
    {
        $response = $this->get('/community');

        $response->assertOk();
        $response->assertSee('Official Feed');
        $response->assertSee('Reny Direct Posts');
        $response->assertSee('Fan Votes');
        $response->assertSee('Country Clubs');
        $response->assertSee('Clubhouse Chat');
        $response->assertSee('Get your Royal Pass');
        $response->assertSee('class="tab is-active"', false);
        $response->assertSee('href="'.url('/community').'"', false);
        $response->assertSee('images/reny-renteria-logo.png');

        $html = $response->getContent();

        $this->assertSame(2, substr_count($html, 'images/reny-renteria-logo.png'));
        $this->assertSame(1, substr_count($html, 'class="community-grid"'));
        $this->assertSame(1, substr_count($html, 'class="side-column"'));
        $this->assertSame(2, substr_count($html, 'class="post-card'));
        $this->assertSame(1, substr_count($html, 'class="vote-card"'));
        $this->assertSame(3, substr_count($html, 'class="club-card"'));
        $this->assertStringNotContainsString('Create group', $html);
        $this->assertStringNotContainsString('Who is going to the first meetup?', $html);
        $this->assertStringNotContainsString('Reny Direct Posts</h1>', $html);
        $this->assertStringNotContainsString('Country Groups', $html);
        $this->assertStringNotContainsString('class="direct-post-card"', $html);
        $this->assertStringNotContainsString('class="poll-card"', $html);
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
                ->assertSee('href="'.url('/community').'"', false)
                ->assertDontSee('href="#community"', false);
        }
    }
}
