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
        $response->assertSee('id="photoLightboxPrev"', false);
        $response->assertSee('id="photoLightboxNext"', false);
        $response->assertSee('id="photoLightboxSave"', false);
        $response->assertSee('id="photoLightboxShare"', false);
        $response->assertSee('id="photoLightboxDeepLink"', false);
        $response->assertSee('id="photoLightboxError"', false);
        $response->assertSee('id="photoToast"', false);

        $html = $response->getContent();

        preg_match_all('/<img[^>]+images\/photos\//', $html, $photoImages);

        $this->assertSame(12, substr_count($html, 'class="photo-tile'));
        $this->assertSame(12, substr_count($html, 'data-photo-slug='));
        $this->assertSame(12, count($photoImages[0]));
        $this->assertStringContainsString('data-photo-slug="capri-heartbreak"', $html);
        $this->assertStringContainsString('data-photo-share-url="'.route('photos', ['photo' => 'capri-heartbreak']).'"', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('youtube', strtolower($html));
        $this->assertStringNotContainsString('i.ytimg.com', strtolower($html));
        $this->assertStringNotContainsString('photo-comment', strtolower($html));
        $this->assertStringNotContainsString('data-photo-comment', strtolower($html));
    }

    public function test_photo_deep_link_query_keeps_gallery_contract_available(): void
    {
        $this->get(route('photos', ['photo' => 'capri-heartbreak']))
            ->assertOk()
            ->assertSee('data-photo-slug="capri-heartbreak"', false)
            ->assertSee('data-photo-share-url="'.route('photos', ['photo' => 'capri-heartbreak']).'"', false)
            ->assertSee('id="photoLightbox"', false);
    }

    public function test_photos_route_stays_available_after_leaving_the_main_navigation(): void
    {
        $this->get('/photos')
            ->assertOk()
            ->assertSee('class="photo-masonry"', false);
    }
}
