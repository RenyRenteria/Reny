<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\MediaAssetType;
use App\Models\CommunityPostReply;
use App\Models\EditorialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityPostCmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_community_post_management_is_authorized_by_admin_role_not_email(): void
    {
        $reny = $this->communityEditor();
        $otherAdmin = User::factory()->create([
            'email' => 'other-admin@example.com',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAsAdmin($reny)
            ->get(route('admin.site-editor.show', ['page' => 'community']))
            ->assertOk()
            ->assertSee('Royal Posts')
            ->assertSee('Publish now')
            ->assertSee('Contenido enriquecido');

        $this->actingAsAdmin($otherAdmin)
            ->get(route('admin.site-editor.show', ['page' => 'community']))
            ->assertOk()
            ->assertSee('Royal Posts')
            ->assertSee('Publish now')
            ->assertDontSee('Módulo restringido');

        $this->actingAsAdmin($otherAdmin)
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload([
                'title' => 'Post by another authorized admin',
                'action' => 'draft',
            ]))
            ->assertRedirect(route('admin.site-editor.show', ['page' => 'community']));

        $this->assertDatabaseHas('editorial_contents', [
            'title' => 'Post by another authorized admin',
            'created_by_id' => $otherAdmin->id,
        ]);
    }

    public function test_editor_can_publish_rich_post_with_cover_embeds_and_safe_html(): void
    {
        Storage::fake('public');
        $reny = $this->communityEditor();

        $this->actingAsAdmin($reny)
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload([
                'title' => 'Primer post de Community',
                'body' => '<h2>Hola comunidad</h2><p>Contenido <strong>completo</strong>.</p><script>alert(1)</script><a href="javascript:alert(2)">link inseguro</a>',
                'published_on' => '2026-07-21',
                'media_urls' => "https://youtu.be/dQw4w9WgXcQ\nhttps://cdn.example.com/audio/update.mp3\nhttps://example.com/more",
                'cover_image' => UploadedFile::fake()->image('community-cover.jpg', 1200, 800),
                'action' => 'publish',
            ]))
            ->assertRedirect(route('admin.site-editor.show', ['page' => 'community']))
            ->assertSessionHas('status', 'Post "Primer post de Community" publicado.');

        $post = EditorialContent::query()->sole();

        $this->assertSame(ContentType::Post, $post->type);
        $this->assertSame(EditorialStatus::Published, $post->status);
        $this->assertStringContainsString('<strong>completo</strong>', $post->body);
        $this->assertStringNotContainsString('<script', $post->body);
        $this->assertStringNotContainsString('javascript:', $post->body);
        $this->assertTrue((bool) data_get($post->metadata, 'comments_enabled'));
        $this->assertSame('embed', data_get($post->metadata, 'media_items.0.type'));
        $this->assertSame('audio', data_get($post->metadata, 'media_items.1.type'));
        $this->assertSame('link', data_get($post->metadata, 'media_items.2.type'));
        $this->assertCount(1, $post->mediaAssets);
        Storage::disk('public')->assertExists($post->mediaAssets->first()->path);

        $this->get('/community')
            ->assertOk()
            ->assertSee('Primer post de Community')
            ->assertSee('<h2>Hola comunidad</h2>', false)
            ->assertSee('<strong>completo</strong>', false)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('<audio controls', false)
            ->assertDontSee('<script', false)
            ->assertDontSee('View Reny note');
    }

    public function test_editor_can_add_and_remove_server_hosted_photo_and_video_attachments(): void
    {
        Storage::fake('public');
        $reny = $this->communityEditor();

        $this->actingAsAdmin($reny)
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload([
                'title' => 'Post con galería local',
                'attachments' => [
                    UploadedFile::fake()->image('backstage.jpg', 1200, 800),
                    UploadedFile::fake()->create('rehearsal.mp4', 256, 'video/mp4'),
                ],
            ]))
            ->assertRedirect(route('admin.site-editor.show', ['page' => 'community']));

        $post = EditorialContent::query()->sole()->load('mediaAssets');
        $image = $post->mediaAssets->firstWhere('type', MediaAssetType::Image);
        $video = $post->mediaAssets->firstWhere('type', MediaAssetType::Video);

        $this->assertNotNull($image);
        $this->assertNotNull($video);
        $this->assertSame('attachment', $image->pivot->role);
        $this->assertSame('attachment', $video->pivot->role);
        Storage::disk('public')->assertExists($image->path);
        Storage::disk('public')->assertExists($video->path);

        $this->get('/royals')
            ->assertOk()
            ->assertSee($image->publicUrl(), false)
            ->assertSee($video->publicUrl(), false)
            ->assertSee('<video controls preload="metadata">', false);

        $removedVideoUrl = $video->publicUrl();

        $this->actingAsAdmin($reny)
            ->patch(route('admin.site-editor.community-posts.update', $post), $this->postPayload([
                'title' => 'Post con galería actualizada',
                'remove_attachment_ids' => [$video->id],
                'attachments' => [UploadedFile::fake()->image('encore.png', 900, 900)],
            ]))
            ->assertRedirect(route('admin.site-editor.show', ['page' => 'community']));

        $post->refresh()->load('mediaAssets');

        $this->assertCount(2, $post->mediaAssets);
        $this->assertFalse($post->mediaAssets->contains($video));
        $this->assertTrue($post->mediaAssets->contains($image));
        $this->assertSame(
            [MediaAssetType::Image, MediaAssetType::Image],
            $post->mediaAssets->pluck('type')->all(),
        );

        $this->get('/royals')
            ->assertOk()
            ->assertSee('Post con galería actualizada')
            ->assertSee($image->publicUrl(), false)
            ->assertDontSee($removedVideoUrl, false);
    }

    public function test_progress_upload_request_returns_json_after_files_are_persisted(): void
    {
        Storage::fake('public');
        $reny = $this->communityEditor();

        $response = $this->actingAsAdmin($reny)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload([
                'title' => 'Post con upload visible',
                'attachments' => [
                    UploadedFile::fake()->image('progress-photo.jpg', 1200, 800),
                    UploadedFile::fake()->create('progress-video.mp4', 512, 'video/mp4'),
                ],
            ]));

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Post "Post con upload visible" publicado.')
            ->assertJsonPath('redirect_url', route('admin.site-editor.show', ['page' => 'community']))
            ->assertJsonPath('post.status', EditorialStatus::Published->value);

        $post = EditorialContent::query()->sole()->load('mediaAssets');

        $this->assertCount(2, $post->mediaAssets);
        $post->mediaAssets->each(fn ($asset) => Storage::disk('public')->assertExists($asset->path));
    }

    public function test_community_video_accepts_exactly_one_gigabyte_and_remains_playable(): void
    {
        Storage::fake('public');
        $reny = $this->communityEditor();
        $oneGigabyteInKilobytes = 1024 * 1024;

        $this->actingAsAdmin($reny)
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload([
                'title' => 'Concierto completo',
                'attachments' => [
                    UploadedFile::fake()->create('concert.mp4', $oneGigabyteInKilobytes, 'video/mp4'),
                ],
            ]))
            ->assertRedirect(route('admin.site-editor.show', ['page' => 'community']))
            ->assertSessionDoesntHaveErrors();

        $post = EditorialContent::query()->sole()->load('mediaAssets');
        $video = $post->mediaAssets->sole();

        $this->assertSame(MediaAssetType::Video, $video->type);
        $this->assertSame(1024 * 1024 * 1024, $video->size_bytes);
        Storage::disk('public')->assertExists($video->path);

        $this->get('/royals')
            ->assertOk()
            ->assertSee($video->publicUrl(), false)
            ->assertSee('<video controls preload="metadata">', false);
    }

    public function test_community_video_over_one_gigabyte_is_rejected_clearly(): void
    {
        Storage::fake('public');
        $reny = $this->communityEditor();

        $this->actingAsAdmin($reny)
            ->from(route('admin.site-editor.show', ['page' => 'community']))
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload([
                'attachments' => [
                    UploadedFile::fake()->create('too-large.mp4', (1024 * 1024) + 1, 'video/mp4'),
                ],
            ]))
            ->assertRedirect(route('admin.site-editor.show', ['page' => 'community']))
            ->assertSessionHasErrors([
                'attachments.0' => 'Cada video puede pesar hasta 1 GB.',
            ]);

        $this->assertDatabaseCount('editorial_contents', 0);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_community_video_limit_is_visible_in_cms_and_server_config(): void
    {
        $reny = $this->communityEditor();

        $this->assertSame(
            1024 * 1024 * 1024,
            config('media.types.'.MediaAssetType::Video->value.'.max_bytes'),
        );

        $this->actingAsAdmin($reny)
            ->get(route('admin.site-editor.show', ['page' => 'community']))
            ->assertOk()
            ->assertSee('data-max-video-bytes="1073741824"', false)
            ->assertSee('Videos: 1 GB maximum each.')
            ->assertSee('data-community-upload-progress-form', false)
            ->assertSee('data-upload-progress', false)
            ->assertSee('data-upload-file-list', false)
            ->assertSee('data-upload-cancel', false)
            ->assertSee('data-upload-retry', false);

        $phpLimits = parse_ini_file(public_path('.user.ini'));

        $this->assertIsArray($phpLimits);
        $this->assertSame('2G', $phpLimits['upload_max_filesize'] ?? null);
        $this->assertSame('13G', $phpLimits['post_max_size'] ?? null);
        $this->assertStringContainsString(
            'client_max_body_size 13G;',
            (string) file_get_contents(base_path('ops/forge/nginx-community-upload-limits.conf')),
        );
    }

    public function test_legacy_royal_posts_are_backfilled_as_manageable_cms_posts(): void
    {
        $reny = $this->communityEditor();
        $migration = require database_path('migrations/2026_08_05_200000_backfill_legacy_community_posts.php');

        $migration->up();

        $this->assertDatabaseHas('editorial_contents', [
            'type' => ContentType::Post->value,
            'slug' => 'studio-note-from-reny',
            'status' => EditorialStatus::Published->value,
        ]);
        $this->assertDatabaseHas('editorial_contents', [
            'type' => ContentType::Post->value,
            'slug' => 'capri-photo-drop',
            'status' => EditorialStatus::Published->value,
        ]);

        $this->get('/royals')
            ->assertOk()
            ->assertSee('Studio note from Reny')
            ->assertSee('Capri photo drop')
            ->assertSee('https://images.unsplash.com/photo-1598488035139-bdbb2231ce04', false)
            ->assertDontSee('Which drop should go first?');

        $this->actingAsAdmin($reny)
            ->get(route('admin.site-editor.show', ['page' => 'community']))
            ->assertOk()
            ->assertSee('Studio note from Reny')
            ->assertSee('Capri photo drop');

        $this->getJson(route('public-content.payload', 'community'))
            ->assertOk()
            ->assertJsonPath('_cms_source', 'cms')
            ->assertJsonPath('_cms_fallback', false)
            ->assertJsonCount(2, 'posts')
            ->assertJsonFragment(['title' => 'Studio note from Reny'])
            ->assertJsonFragment(['title' => 'Capri photo drop']);

        $this->get('/royals')
            ->assertOk()
            ->assertSee('Studio note from Reny')
            ->assertSee('Capri photo drop');
    }

    public function test_post_attachments_reject_unsafe_file_extensions(): void
    {
        Storage::fake('public');
        $reny = $this->communityEditor();

        $this->actingAsAdmin($reny)
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload([
                'attachments' => [
                    UploadedFile::fake()->create('not-a-video.php', 16, 'video/mp4'),
                ],
            ]))
            ->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('editorial_contents', 0);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_editor_can_save_draft_schedule_and_order_published_posts_by_date(): void
    {
        $reny = $this->communityEditor();

        $this->actingAsAdmin($reny)
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload([
                'title' => 'Borrador interno',
                'action' => 'draft',
            ]))
            ->assertRedirect();

        $this->actingAsAdmin($reny)
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload([
                'title' => 'Programado futuro',
                'action' => 'schedule',
                'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ]))
            ->assertRedirect();

        foreach ([
            ['title' => 'Post más reciente', 'published_on' => '2026-07-21'],
            ['title' => 'Post anterior', 'published_on' => '2026-07-18'],
        ] as $payload) {
            $this->actingAsAdmin($reny)
                ->post(route('admin.site-editor.community-posts.store'), $this->postPayload($payload))
                ->assertRedirect();
        }

        $this->assertDatabaseHas('editorial_contents', [
            'title' => 'Borrador interno',
            'status' => EditorialStatus::Draft->value,
        ]);
        $this->assertDatabaseHas('editorial_contents', [
            'title' => 'Programado futuro',
            'status' => EditorialStatus::Scheduled->value,
        ]);

        $response = $this->get('/community')->assertOk();
        $html = $response->getContent();

        $response->assertDontSee('Borrador interno')->assertDontSee('Programado futuro');
        $this->assertLessThan(
            strpos($html, 'Post anterior'),
            strpos($html, 'Post más reciente'),
        );
    }

    public function test_publishing_scheduled_post_now_clears_schedule_and_shows_it_in_feed(): void
    {
        $reny = $this->communityEditor();
        $futureSchedule = now()->addDay()->format('Y-m-d\TH:i');

        $this->actingAsAdmin($reny)
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload([
                'title' => 'Programado para publicar ahora',
                'action' => 'schedule',
                'scheduled_at' => $futureSchedule,
            ]))
            ->assertRedirect();

        $post = EditorialContent::query()->sole();

        $this->get('/community')
            ->assertOk()
            ->assertDontSee('Programado para publicar ahora');

        $this->actingAsAdmin($reny)
            ->patch(route('admin.site-editor.community-posts.update', $post), $this->postPayload([
                'title' => 'Programado para publicar ahora',
                'action' => 'publish',
                'scheduled_at' => $futureSchedule,
            ]))
            ->assertRedirect(route('admin.site-editor.show', ['page' => 'community']))
            ->assertSessionHas('status', 'Post "Programado para publicar ahora" publicado.');

        $post->refresh();

        $this->assertSame(EditorialStatus::Published, $post->status);
        $this->assertNull($post->scheduled_at);

        $this->get('/community')
            ->assertOk()
            ->assertSee('Programado para publicar ahora');
    }

    public function test_royal_account_can_comment_and_editor_can_hide_or_delete_it(): void
    {
        $reny = $this->communityEditor();
        $fan = User::factory()->royal()->create(['name' => 'Fan con Royal Pass']);

        $this->actingAsAdmin($reny)
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload([
                'title' => 'Post comentable',
            ]))
            ->assertRedirect();

        $post = EditorialContent::query()->sole();
        $postKey = 'cms-post-'.$post->id;

        $this->actingAs($fan)
            ->postJson(route('community.posts.replies.store', $postKey), ['body' => 'Comentario para moderar.'])
            ->assertCreated();

        $comment = CommunityPostReply::query()->sole();

        $this->actingAsAdmin($reny)
            ->get(route('admin.site-editor.show', ['page' => 'community']))
            ->assertOk()
            ->assertSee('Comentario para moderar.');

        $this->actingAsAdmin($reny)
            ->patch(route('admin.site-editor.community-comments.moderate', $comment), ['action' => 'hide'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_post_replies', [
            'id' => $comment->id,
            'status' => 'removed',
        ]);
        $this->get('/community')->assertDontSee('Comentario para moderar.');

        $this->actingAsAdmin($reny)
            ->patch(route('admin.site-editor.community-comments.moderate', $comment), ['action' => 'delete'])
            ->assertRedirect();

        $this->assertDatabaseCount('community_post_replies', 0);
    }

    private function communityEditor(): User
    {
        return User::factory()->create([
            'email' => 'reny@portierstrategy.com',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function postPayload(array $overrides = []): array
    {
        return [
            'title' => 'Post publicado',
            'body' => '<p>Contenido completo del post.</p>',
            'published_on' => '2026-07-21',
            'scheduled_at' => null,
            'comments_enabled' => '1',
            'media_urls' => '',
            'action' => 'publish',
            ...$overrides,
        ];
    }

    private function actingAsAdmin(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
