<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhotosPageTest extends TestCase
{
    public function test_photos_page_mounts_image_only_masonry_gallery(): void
    {
        $response = $this->get('/photos');

        $response->assertOk();
        $response->assertSee('PHOTOS');
        $response->assertSee('class="photo-masonry"', false);
        $response->assertSee('class="tab is-active"', false);
        $response->assertSee('href="' . url('/photos') . '"', false);

        $html = $response->getContent();

        preg_match_all('/<img[^>]+images\/photos\//', $html, $photoImages);

        $this->assertSame(12, substr_count($html, 'class="photo-tile'));
        $this->assertSame(12, count($photoImages[0]));
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('youtube', strtolower($html));
        $this->assertStringNotContainsString('i.ytimg.com', strtolower($html));
    }

    public function test_music_and_videos_link_to_photos_route(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="' . url('/photos') . '"', false)
            ->assertDontSee('href="#photos"', false);

        $this->get('/videos')
            ->assertOk()
            ->assertSee('href="' . url('/photos') . '"', false)
            ->assertDontSee('href="#photos"', false);
    }
}
