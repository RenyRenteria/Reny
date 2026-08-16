<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\SitePageSetting;
use App\Models\User;
use App\Services\Admin\VideoCatalogService;
use App\Services\PublicCms\PayloadMediaResolver;
use App\Services\PublicVideoCatalogSeeder;
use App\Support\VideoCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VideoCmsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_public_catalog_import_is_idempotent_and_preserves_the_existing_public_inventory(): void
    {
        $firstImport = app(PublicVideoCatalogSeeder::class)->seed();
        $secondImport = app(PublicVideoCatalogSeeder::class)->seed();

        $this->assertSame(22, $firstImport['created']);
        $this->assertTrue($firstImport['pageSettingsCreated']);
        $this->assertSame(0, $secondImport['created']);
        $this->assertSame(22, $secondImport['existing']);
        $this->assertDatabaseCount('editorial_contents', 22);
        $this->assertDatabaseHas('site_page_settings', [
            'page' => 'videos',
            'section' => 'page_settings',
            'status' => SitePageSetting::STATUS_PUBLISHED,
        ]);

        $payload = $this->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertJsonCount(6, 'music_videos')
            ->assertJsonCount(2, 'series')
            ->assertJsonCount(6, 'performances')
            ->assertJsonCount(4, 'behind_the_scenes')
            ->assertJsonCount(3, 'vlogs')
            ->assertJsonPath('featured_video.id', 'UWDLtZCoTag')
            ->json();

        $expectedIds = collect(config('reny_videos.groups'))->map(
            fn (array $videos): array => collect($videos)->pluck('youtube_id')->all()
        );

        foreach ($expectedIds as $group => $ids) {
            $this->assertSame($ids, collect($payload[$group])->pluck('id')->all());
        }

        $html = $this->get('/videos')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<iframe'));
        $this->assertSame(19, substr_count($html, 'class="video-load-button"'));
        $this->assertSame(2, substr_count($html, 'class="playlist-card"'));

        $resolver = app(PayloadMediaResolver::class);
        $this->assertSame('Ue8orNrHw9s', $resolver->youtubeId('https://www.youtube.com/watch?v=Ue8orNrHw9s'));
        $this->assertNull($resolver->youtubeId('https://example.com/?v=Ue8orNrHw9s'));
        $this->assertSame('PL123456789', $resolver->youtubePlaylistId('https://www.youtube.com/playlist?list=PL123456789'));
    }

    public function test_video_site_editor_renders_the_functional_catalog_from_cms_records(): void
    {
        app(PublicVideoCatalogSeeder::class)->seed();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'videos']))
            ->assertOk()
            ->assertSee('Organizar catálogo')
            ->assertSee('Agregar video')
            ->assertSee('Agregar playlist')
            ->assertSee('Banner y destacado')
            ->assertSee('Take a bite (Official Music Video)')
            ->assertSee('I Swear')
            ->assertSee('Visitando Mas23')
            ->assertSee('data-video-sort-list="music_videos"', false)
            ->assertSee(route('admin.site-editor.videos.order'), false)
            ->assertSee(route('admin.site-editor.videos.featured'), false)
            ->assertSee('data-video-content-form', false)
            ->assertSee('iframe title="Preview público de Videos"', false);
    }

    public function test_admin_can_publish_a_video_as_featured_without_removing_it_from_its_collection(): void
    {
        app(PublicVideoCatalogSeeder::class)->seed();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $this->post(route('admin.content.store'), [
            'return_to_video_editor' => '1',
            '_video_editor_tab' => 'add-video',
            'action' => 'publish',
            'type' => ContentType::Video->value,
            'title' => 'New Featured Performance',
            'summary' => 'Live from Panama',
            'visibility' => VisibilityAudience::Open->value,
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/shorts/abc123XYZ89',
                'category' => 'performances',
                'access_tier' => VisibilityAudience::Open->value,
                'sort_order' => 0,
                'is_featured' => true,
            ],
        ])->assertRedirect(route('admin.site-editor.show', ['page' => 'videos']));

        $created = EditorialContent::query()->where('title', 'New Featured Performance')->sole();
        $this->assertTrue(VideoCatalog::isFeatured($created));
        $this->assertSame(1, EditorialContent::query()
            ->where('type', ContentType::Video->value)
            ->get()
            ->filter(fn (EditorialContent $content): bool => VideoCatalog::isFeatured($content))
            ->count());

        $this->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertJsonPath('featured_video.id', 'abc123XYZ89')
            ->assertJsonPath('performances.0.title', 'New Featured Performance')
            ->assertJsonCount(7, 'performances');
    }

    public function test_video_editor_accepts_youtube_playlists_and_rejects_other_hosts(): void
    {
        app(PublicVideoCatalogSeeder::class)->seed();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);
        $payload = [
            'return_to_video_editor' => '1',
            '_video_editor_tab' => 'add-playlist',
            'action' => 'publish',
            'type' => ContentType::Video->value,
            'title' => 'New CMS Playlist',
            'summary' => 'Playlist validation',
            'visibility' => VisibilityAudience::Open->value,
            'metadata' => [
                'youtube_url' => 'https://example.com/?v=abc123XYZ89',
                'category' => 'series',
                'access_tier' => VisibilityAudience::Open->value,
                'sort_order' => 999,
                'is_featured' => false,
            ],
        ];

        $this->post(route('admin.content.store'), $payload)
            ->assertSessionHasErrors('metadata.youtube_url');
        $this->assertDatabaseMissing('editorial_contents', ['title' => 'New CMS Playlist']);

        data_set($payload, 'metadata.youtube_url', 'https://www.youtube.com/playlist?list=PL123456789');
        $this->post(route('admin.content.store'), $payload)
            ->assertRedirect(route('admin.site-editor.show', ['page' => 'videos']));

        $this->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertJsonPath('series.2.title', 'New CMS Playlist')
            ->assertJsonPath('series.2.external_url', 'https://www.youtube.com/playlist?list=PL123456789')
            ->assertJsonPath('series.2.play_state', 'ready');
    }

    public function test_playlist_cannot_be_featured_or_remove_the_public_hero(): void
    {
        app(PublicVideoCatalogSeeder::class)->seed();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);
        $payload = [
            'return_to_video_editor' => '1',
            '_video_editor_tab' => 'add-playlist',
            'action' => 'publish',
            'type' => ContentType::Video->value,
            'title' => 'Invalid Featured Playlist',
            'summary' => 'Must not replace the public hero',
            'visibility' => VisibilityAudience::Open->value,
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/playlist?list=PL123456789',
                'category' => 'series',
                'access_tier' => VisibilityAudience::Open->value,
                'sort_order' => 999,
                'is_featured' => true,
            ],
        ];

        $this->post(route('admin.content.store'), $payload)
            ->assertSessionHasErrors('metadata.is_featured');
        $this->assertDatabaseMissing('editorial_contents', ['title' => 'Invalid Featured Playlist']);

        $playlist = EditorialContent::query()
            ->where('type', ContentType::Video->value)
            ->get()
            ->first(fn (EditorialContent $content): bool => VideoCatalog::groupFor($content) === 'series');
        $this->assertNotNull($playlist);

        $this->post(route('admin.site-editor.videos.featured'), ['video_id' => $playlist->id])
            ->assertUnprocessable();

        $this->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertJsonPath('featured_video.id', 'UWDLtZCoTag');
        $this->assertSame(1, substr_count($this->get('/videos')->assertOk()->getContent(), '<iframe'));
    }

    public function test_playlist_forms_do_not_render_the_featured_checkbox(): void
    {
        app(PublicVideoCatalogSeeder::class)->seed();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $html = $this->get(route('admin.site-editor.show', ['page' => 'videos']))
            ->assertOk()
            ->getContent();
        preg_match_all('/<form[^>]+data-video-content-kind="playlist"[^>]*>(.*?)<\/form>/s', $html, $matches);

        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $playlistForm) {
            $this->assertStringNotContainsString('type="checkbox" value="1"', $playlistForm);
        }
    }

    public function test_public_payload_ignores_a_legacy_featured_playlist(): void
    {
        app(PublicVideoCatalogSeeder::class)->seed();
        $contents = EditorialContent::query()->where('type', ContentType::Video->value)->get();

        foreach ($contents as $content) {
            $metadata = $content->metadata ?? [];
            $metadata['is_featured'] = VideoCatalog::groupFor($content) === 'series';
            $content->forceFill(['metadata' => $metadata])->saveQuietly();
        }

        $this->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertJsonPath('featured_video.id', 'UWDLtZCoTag');
        $this->assertSame(1, substr_count($this->get('/videos')->assertOk()->getContent(), '<iframe'));
    }

    public function test_complete_browser_order_persists_after_reload_without_changing_other_groups(): void
    {
        app(PublicVideoCatalogSeeder::class)->seed();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $catalog = app(VideoCatalogService::class)->contents()
            ->reject(fn (EditorialContent $content): bool => VideoCatalog::isFeaturedOnly($content))
            ->values();
        $originalIdsByGroup = collect(VideoCatalog::groups())->mapWithKeys(
            fn (array $group, string $groupKey): array => [
                $groupKey => $catalog
                    ->filter(fn (EditorialContent $content): bool => VideoCatalog::groupFor($content) === $groupKey)
                    ->pluck('id')
                    ->values()
                    ->all(),
            ],
        );
        $reorderedMusicIds = array_reverse($originalIdsByGroup['music_videos']);
        $completeOrder = collect(VideoCatalog::groups())->flatMap(
            fn (array $group, string $groupKey): array => $groupKey === 'music_videos'
                ? $reorderedMusicIds
                : $originalIdsByGroup[$groupKey],
        )->values()->all();

        $this->post(route('admin.site-editor.videos.order'), [
            'video_ids' => $completeOrder,
        ])->assertRedirect(route('admin.site-editor.show', ['page' => 'videos']));

        $reloadedHtml = $this->get(route('admin.site-editor.show', ['page' => 'videos']))
            ->assertOk()
            ->getContent();
        preg_match_all('/<article\b(?=[^>]*\bdata-video-row\b)[^>]*\bdata-video-id="(\d+)"/s', $reloadedHtml, $matches);
        $this->assertSame($completeOrder, array_map('intval', $matches[1]));

        $payload = $this->getJson(route('public-content.payload', 'videos'))->assertOk()->json();
        $titlesFor = fn (array $ids): array => collect($ids)
            ->map(fn (int $id): string => $catalog->firstWhere('id', $id)->title)
            ->all();

        $this->assertSame(
            $titlesFor($reorderedMusicIds),
            collect($payload['music_videos'])->pluck('title')->all(),
        );
        foreach (array_keys(VideoCatalog::groups()) as $groupKey) {
            if ($groupKey !== 'music_videos') {
                $this->assertSame($titlesFor($originalIdsByGroup[$groupKey]), collect($payload[$groupKey])->pluck('title')->all());
            }
        }
        $this->assertSame('UWDLtZCoTag', data_get($payload, 'featured_video.id'));
        $this->assertCount(19, collect($payload)->only(array_keys(VideoCatalog::groups()))->except('series')->flatten(1));
        $this->assertCount(2, $payload['series']);
    }

    public function test_reorder_rejects_an_incomplete_catalog_payload(): void
    {
        app(PublicVideoCatalogSeeder::class)->seed();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);
        $catalogIds = app(VideoCatalogService::class)->contents()
            ->reject(fn (EditorialContent $content): bool => VideoCatalog::isFeaturedOnly($content))
            ->pluck('id')
            ->values()
            ->all();

        array_pop($catalogIds);

        $this->post(route('admin.site-editor.videos.order'), [
            'video_ids' => $catalogIds,
        ])->assertUnprocessable();
    }

    public function test_non_publisher_cannot_reorder_or_change_the_featured_video(): void
    {
        app(PublicVideoCatalogSeeder::class)->seed();
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $video = EditorialContent::query()->where('status', EditorialStatus::Published->value)->firstOrFail();
        $this->actingAsAdmin($editor);

        $this->post(route('admin.site-editor.videos.order'), ['video_ids' => [$video->id]])
            ->assertForbidden();
        $this->post(route('admin.site-editor.videos.featured'), ['video_id' => $video->id])
            ->assertForbidden();
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
