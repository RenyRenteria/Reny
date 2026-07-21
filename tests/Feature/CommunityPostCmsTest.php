<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
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

    public function test_only_configured_editor_can_mutate_community_posts(): void
    {
        $reny = $this->communityEditor();
        $otherAdmin = User::factory()->create([
            'email' => 'other-admin@example.com',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAsAdmin($reny)
            ->get(route('admin.site-editor.show', ['page' => 'community']))
            ->assertOk()
            ->assertSee('Community Posts')
            ->assertSee('Publicar ahora')
            ->assertSee('Contenido enriquecido');

        $this->actingAsAdmin($otherAdmin)
            ->get(route('admin.site-editor.show', ['page' => 'community']))
            ->assertOk()
            ->assertSee('Módulo restringido')
            ->assertDontSee('Publicar ahora');

        $this->actingAsAdmin($otherAdmin)
            ->post(route('admin.site-editor.community-posts.store'), $this->postPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('editorial_contents', 0);
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

    public function test_logged_in_account_can_comment_and_editor_can_hide_or_delete_it(): void
    {
        $reny = $this->communityEditor();
        $fan = User::factory()->create(['name' => 'Fan con login']);

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
