<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhotosPageTest extends TestCase
{
    public function test_photos_page_mounts_image_only_masonry_gallery(): void
    {
        $response = $this->get('/photos');

        $response->assertOk();
        $response->assertSee('<title>Photos | Reny Renteria</title>', false);
        $response->assertSee('class="photo-masonry"', false);
        $response->assertDontSee('class="tab is-active"', false);

        $html = $response->getContent();

        preg_match_all('/<img[^>]+images\/photos\//', $html, $photoImages);

        $this->assertSame(12, substr_count($html, 'class="photo-tile'));
        $this->assertSame(12, count($photoImages[0]));
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('youtube', strtolower($html));
        $this->assertStringNotContainsString('i.ytimg.com', strtolower($html));
    }

    public function test_photos_route_stays_available_after_leaving_the_main_navigation(): void
    {
        $this->get('/photos')
            ->assertOk()
            ->assertSee('class="photo-masonry"', false);
    }
}
