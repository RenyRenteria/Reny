<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\SitePageSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminStorefrontSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_admin_can_open_storefront_editor_when_cms_is_enabled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'store']))
            ->assertOk()
            ->assertSee('Event 1')
            ->assertSee('Event 2')
            ->assertSee('Album')
            ->assertSee('Crown Collection')
            ->assertSee('Countdown fecha/hora')
            ->assertSee('Guardar y publicar');
    }

    public function test_admin_can_open_home_storefront_editor_when_cms_is_enabled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'home']))
            ->assertOk()
            ->assertSee('Home / controla Royal Pass, eventos y album deluxe usados en la portada publica.')
            ->assertSee('Royal Pass banner')
            ->assertSee('Event 1')
            ->assertSee('Event 2')
            ->assertSee('Album')
            ->assertSee('Countdown fecha/hora')
            ->assertSee('name="return_page" value="home"', false)
            ->assertDontSee('Payload publico');
    }

    public function test_home_storefront_editor_updates_shared_public_home_payload(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $album = EditorialContent::factory()->published()->create([
            'type' => ContentType::DeluxeAlbum->value,
            'title' => 'Home Deluxe Album',
            'summary' => 'Home selected deluxe content',
            'purchase_key' => 'home-deluxe',
            'metadata' => [
                'price_cents' => 2400,
                'currency' => 'USD',
                'checkout_enabled' => true,
                'is_active' => true,
            ],
        ]);

        $this->actingAsAdmin($admin);

        $this->post(route('admin.site-editor.storefront.update'), $this->storefrontPayload([
            'return_page' => 'home',
            'slots' => [
                'event_primary' => [
                    'title' => 'Home CMS Show',
                    'description' => "Teatro CMS\nNov 5 - 8:00 PM",
                    'price_label' => 'FREE',
                    'cta_label' => 'RESERVE',
                    'countdown_at' => '2026-11-05T20:00',
                    'action_type' => 'rsvp',
                    'product_key' => 'home-cms-show',
                ],
                'album' => [
                    'content_id' => $album->id,
                    'title' => 'Home Deluxe Album',
                    'description' => 'Home selected deluxe content',
                    'cta_label' => 'BUY DELUXE',
                    'action_type' => 'buy',
                    'product_key' => 'home-deluxe',
                ],
            ],
        ]))->assertRedirect(route('admin.site-editor.show', ['page' => 'home']));

        $this->assertDatabaseHas('site_page_settings', [
            'page' => 'store',
            'section' => 'storefront',
            'status' => SitePageSetting::STATUS_PUBLISHED,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Home CMS Show')
            ->assertSee('RESERVE')
            ->assertSee('data-free-event-rsvp="home-cms-show"', false)
            ->assertSee('Home Deluxe Album')
            ->assertSee('Home selected deluxe content')
            ->assertSee('data-buy="home-deluxe"', false)
            ->assertSee(route('store.checkout', ['product' => 'home-deluxe']), false);
    }

    public function test_storefront_event_update_syncs_to_cms_and_public_home_and_store(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get('/')->assertOk()->assertSee('Reny Renteria en Concierto');

        $this->post(route('admin.site-editor.storefront.update'), $this->storefrontPayload([
            'return_page' => 'store',
            'slots' => [
                'event_primary' => [
                    'title' => 'Synced Storefront Show',
                    'description' => "Teatro Nacional\nDec 24 - 8:30 PM",
                    'price_label' => 'FREE',
                    'cta_label' => 'JOIN LIST',
                    'countdown_at' => '2026-12-24T20:30',
                    'action_type' => 'rsvp',
                    'product_key' => 'synced-storefront-show',
                ],
            ],
        ]))->assertRedirect(route('admin.site-editor.show', ['page' => 'store']));

        $this->get(route('admin.site-editor.show', ['page' => 'home']))
            ->assertOk()
            ->assertSee('Synced Storefront Show')
            ->assertSee('synced-storefront-show');

        $this->get(route('admin.site-editor.show', ['page' => 'store']))
            ->assertOk()
            ->assertSee('Synced Storefront Show')
            ->assertSee('synced-storefront-show');

        $this->get('/store')
            ->assertOk()
            ->assertSee('Synced Storefront Show')
            ->assertSee('JOIN LIST')
            ->assertSee('data-free-event-rsvp="synced-storefront-show"', false)
            ->assertDontSee('Reny Renteria en Concierto');

        $this->get('/')
            ->assertOk()
            ->assertSee('Synced Storefront Show')
            ->assertSee('JOIN LIST')
            ->assertSee('data-free-event-rsvp="synced-storefront-show"', false)
            ->assertDontSee('Reny Renteria en Concierto');

        $this->getJson(route('public-content.payload', 'home'))
            ->assertOk()
            ->assertJsonPath('storefront.slots.event_primary.title', 'Synced Storefront Show')
            ->assertJsonPath('events.0.title', 'Synced Storefront Show');
    }

    public function test_published_storefront_settings_feed_public_store(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $eventAsset = $this->mediaAsset('CMS event image', 'store/cms-event.png');
        $albumAsset = $this->mediaAsset('CMS album image', 'store/cms-album.png');
        $album = EditorialContent::factory()->published()->create([
            'type' => ContentType::DeluxeAlbum->value,
            'title' => 'CMS Deluxe Album',
            'summary' => 'CMS selected album summary',
            'purchase_key' => 'cms-deluxe',
            'metadata' => [
                'price_cents' => 2400,
                'currency' => 'USD',
                'checkout_enabled' => true,
                'is_active' => true,
            ],
        ]);
        $album->mediaAssets()->attach($albumAsset->id, ['role' => 'cover', 'sort_order' => 0]);

        $this->actingAsAdmin($admin);

        $this->post(route('admin.site-editor.storefront.update'), $this->storefrontPayload([
            'slots' => [
                'event_primary' => [
                    'title' => 'CMS Free Night',
                    'description' => "Venue CMS\nJan 10 - 8:00 PM",
                    'price_label' => 'FREE',
                    'cta_label' => 'SAVE SEAT',
                    'countdown_at' => '2026-11-02T20:45',
                    'action_type' => 'rsvp',
                    'product_key' => 'cms-free',
                    'image_asset_id' => $eventAsset->id,
                ],
                'album' => [
                    'content_id' => $album->id,
                    'cta_label' => 'GET CMS DELUXE',
                    'action_type' => 'buy',
                    'product_key' => 'deluxe',
                ],
            ],
        ]))->assertRedirect(route('admin.site-editor.show', ['page' => 'store']));

        $this->assertDatabaseHas('site_page_settings', [
            'page' => 'store',
            'section' => 'storefront',
            'status' => SitePageSetting::STATUS_PUBLISHED,
        ]);

        $this->get('/store')
            ->assertOk()
            ->assertSee('CMS Free Night')
            ->assertSee('SAVE SEAT')
            ->assertSee('data-free-event-rsvp="cms-free"', false)
            ->assertSee('data-countdown-at="2026-11-02T20:45:00-05:00"', false)
            ->assertSee('/storage/store/cms-event.png', false)
            ->assertSee('CMS Deluxe Album')
            ->assertSee('CMS selected album summary')
            ->assertSee('data-buy="cms-deluxe"', false)
            ->assertSee('/storage/store/cms-album.png', false);

        $this->getJson(route('public-content.payload', 'store'))
            ->assertOk()
            ->assertJsonPath('storefront.slots.event_primary.title', 'CMS Free Night')
            ->assertJsonPath('storefront.slots.event_primary.countdown_at', '2026-11-02T20:45')
            ->assertJsonPath('storefront.slots.album.title', 'CMS Deluxe Album')
            ->assertJsonPath('storefront.slots.album.product_key', 'cms-deluxe')
            ->assertJsonPath('storefront.slots.album.price_label', '$24');
    }

    private function mediaAsset(string $title, string $path): MediaAsset
    {
        return MediaAsset::create([
            'type' => MediaAssetType::Image->value,
            'title' => $title,
            'disk' => 'public',
            'path' => $path,
            'original_filename' => basename($path),
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 100,
            'is_public' => true,
            'alt_text' => $title,
            'processing_status' => MediaProcessingStatus::Ready->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storefrontPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'action' => 'publish',
            'royal_pass' => [
                'copy_before' => 'Get your',
                'emphasis' => 'Royal Pass',
                'copy_after' => 'to unlock exclusive content, community and more',
                'cta_label' => 'Unlock Royal Pass',
                'product_key' => 'royal',
            ],
            'slots' => [
                'event_primary' => [
                    'title' => 'Reny Renteria en Concierto',
                    'description' => "Rock & Folk Pty, Ciudad de Panama\n21/ Sep - 7:30 PM",
                    'price_label' => 'FREE',
                    'cta_label' => 'GET TICKETS',
                    'countdown_at' => '2026-09-21T19:30',
                    'action_type' => 'rsvp',
                    'product_key' => 'concert',
                ],
                'event_secondary' => [
                    'title' => 'Festival de la Rosa Dorada',
                    'description' => "Rock & Folk Pty, Ciudad de Panama\n16/ Dic - 7:30 PM",
                    'price_label' => '$15',
                    'cta_label' => 'GET TICKETS',
                    'countdown_at' => '2026-12-16T19:30',
                    'action_type' => 'buy',
                    'product_key' => 'listening',
                ],
                'album' => [
                    'title' => 'Work in Progress',
                    'eyebrow' => 'Deluxe Album',
                    'description' => 'Includes: Tracks, BTS, Notes, Videos',
                    'cta_label' => 'GET DELUXE',
                    'action_type' => 'buy',
                    'product_key' => 'deluxe',
                ],
                'merch' => [
                    'title' => 'Crown Collection',
                    'description' => 'Limited edition merch package',
                    'cta_label' => 'GET MERCH',
                    'action_type' => 'buy',
                    'product_key' => 'merch',
                ],
            ],
        ], $overrides);
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
