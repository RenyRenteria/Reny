<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\SitePageSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMusicBannerSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_admin_can_open_music_banner_editor_when_cms_is_enabled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'music']))
            ->assertOk()
            ->assertSee('href="'.url('/music').'"', false)
            ->assertSee('Banner')
            ->assertSee('Guardar y publicar')
            ->assertSee('Biggest')
            ->assertSee('Comeback Album!');
    }

    public function test_draft_banner_does_not_change_public_music_banner_until_published(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->post(route('admin.site-editor.music-banner.update'), $this->bannerPayload([
            'action' => 'draft',
            'title_line_1' => 'Draft',
            'title_line_2' => 'Only',
        ]))->assertRedirect(route('admin.site-editor.show', ['page' => 'music']));

        $this->assertDatabaseHas('site_page_settings', [
            'page' => 'music',
            'section' => 'banner',
            'status' => SitePageSetting::STATUS_DRAFT,
        ]);

        $this->get(route('music'))
            ->assertOk()
            ->assertSee('Biggest')
            ->assertDontSee('Draft');

        $this->post(route('admin.site-editor.music-banner.update'), $this->bannerPayload([
            'action' => 'publish',
            'title_line_1' => 'Published',
            'title_line_2' => 'Now',
            'destination_url' => 'https://example.com/music-drop',
        ]))->assertRedirect(route('admin.site-editor.show', ['page' => 'music']));

        $this->assertDatabaseHas('site_page_settings', [
            'page' => 'music',
            'section' => 'banner',
            'status' => SitePageSetting::STATUS_PUBLISHED,
        ]);
        $this->assertDatabaseMissing('site_page_settings', [
            'page' => 'music',
            'section' => 'banner',
            'status' => SitePageSetting::STATUS_DRAFT,
        ]);

        $this->get(route('music'))
            ->assertOk()
            ->assertSee('Published')
            ->assertSee('Now')
            ->assertSee('https://example.com/music-drop');

        $this->getJson(route('public-content.payload', 'music'))
            ->assertOk()
            ->assertJsonPath('banner.title_line_1', 'Published')
            ->assertJsonPath('banner.title_line_2', 'Now')
            ->assertJsonPath('banner.destination_url', 'https://example.com/music-drop');
    }

    public function test_published_banner_can_upload_public_artwork(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->post(route('admin.site-editor.music-banner.update'), [
            ...$this->bannerPayload([
                'action' => 'publish',
                'title_line_1' => 'Artwork',
                'title_line_2' => 'Drop',
            ]),
            'image' => UploadedFile::fake()->image('banner.jpg')->size(512),
        ])->assertRedirect(route('admin.site-editor.show', ['page' => 'music']));

        $asset = MediaAsset::query()->firstOrFail();

        $this->assertTrue($asset->is_public);
        $this->assertSame('Music banner artwork', $asset->title);

        $this->assertDatabaseHas('site_page_settings', [
            'page' => 'music',
            'section' => 'banner',
            'status' => SitePageSetting::STATUS_PUBLISHED,
            'media_asset_id' => $asset->id,
        ]);

        $this->get(route('music'))
            ->assertOk()
            ->assertSee('Artwork')
            ->assertSee('has-uploaded-art', false)
            ->assertSee($asset->publicUrl(), false);
    }

    public function test_public_music_banner_no_longer_renders_disc_badge_but_keeps_ribbon(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->post(route('admin.site-editor.music-banner.update'), $this->bannerPayload([
            'action' => 'publish',
            'title_line_1' => 'Badge',
            'title_line_2' => 'Gone',
        ]))->assertRedirect(route('admin.site-editor.show', ['page' => 'music']));

        $this->get(route('music'))
            ->assertOk()
            ->assertDontSee('disc-badge', false)
            ->assertSee('artist-sticker', false)
            ->assertSee('THE FIRST ALBUM');

        $this->get(route('admin.site-editor.show', ['page' => 'music']))
            ->assertOk()
            ->assertDontSee('disc-badge', false)
            ->assertSee('artist-sticker', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bannerPayload(array $overrides = []): array
    {
        return [
            'action' => 'publish',
            'eyebrow_line_1' => 'First',
            'eyebrow_line_2' => 'Album',
            'title_line_1' => 'Biggest',
            'title_line_2' => 'Launch',
            'subtitle' => 'Comeback Album!',
            'description' => 'A cinematic release package for Reny Renteria, built around a lead album, featured tracks, fan updates, and premium music drops.',
            'footer_line_1' => 'Visit us today at',
            'footer_line_2' => 'renyrenteria.com',
            'destination_url' => 'https://renyrenteria.com',
            'sticker_line_1' => 'THE FIRST ALBUM',
            'sticker_line_2' => 'BANO #1',
            'status' => 'published',
            ...$overrides,
        ];
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
