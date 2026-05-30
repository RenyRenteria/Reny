<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_videos_page_only_loads_the_featured_youtube_iframe_initially(): void
    {
        $response = $this->get('/videos');

        $response->assertStatus(200);

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, '<iframe'));
        $this->assertSame(19, substr_count($html, 'class="video-load-button"'));
        $this->assertStringContainsString('data-youtube-id="mfaOU7LFheE"', $html);
    }
}
