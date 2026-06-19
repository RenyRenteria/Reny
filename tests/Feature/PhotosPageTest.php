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
        $response->assertSee('href="'.url('/photos').'"', false);
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
        $this->assertStringContainsString('data-photo-share-url="'.url('/photos?photo=capri-heartbreak').'"', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('youtube', strtolower($html));
        $this->assertStringNotContainsString('i.ytimg.com', strtolower($html));
        $this->assertStringNotContainsString('photo-comment', strtolower($html));
        $this->assertStringNotContainsString('data-photo-comment', strtolower($html));
    }

    public function test_photo_deep_link_query_keeps_gallery_contract_available(): void
    {
        $response = $this->get('/photos?photo=capri-heartbreak');

        $response->assertOk();
        $response->assertSee('data-photo-slug="capri-heartbreak"', false);
        $response->assertSee('data-photo-share-url="'.url('/photos?photo=capri-heartbreak').'"', false);
        $response->assertSee('id="photoLightbox"', false);
    }

    public function test_music_and_videos_link_to_photos_route(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.url('/photos').'"', false)
            ->assertDontSee('href="#photos"', false);

        $this->get('/videos')
            ->assertOk()
            ->assertSee('href="'.url('/photos').'"', false)
            ->assertDontSee('href="#photos"', false);
    }
}
