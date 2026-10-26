<?php

namespace Tests\Feature;

use App\Models\CommunityVideoView;
use App\Models\User;
use App\Services\CommunityInteractionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommunityVideoViewsTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO_URL = 'https://cdn.example.com/royals/backstage.mp4';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        config()->set('reny_catalog.community.posts', [[
            'key' => 'backstage-post',
            'title' => 'Backstage con Reny',
            'body' => 'Mira el ensayo.',
            'media_items' => [self::VIDEO_URL],
        ]]);
    }

    public function test_each_guest_video_playback_is_counted_and_rendered_in_the_feed(): void
    {
        $video = $this->videoPayload();

        $this->postJson($video['view_endpoint'])
            ->assertOk()
            ->assertJsonPath('counted', true)
            ->assertJsonPath('view_count', 1);

        $this->postJson($video['view_endpoint'])
            ->assertOk()
            ->assertJsonPath('counted', true)
            ->assertJsonPath('view_count', 2);

        $this->assertDatabaseCount('community_video_views', 2);
        $this->get('/royals')
            ->assertOk()
            ->assertSee('data-community-video-view', false)
            ->assertSee('data-view-endpoint="'.$video['view_endpoint'].'"', false)
            ->assertSee('data-video-view-count-value>2</span>', false);
    }

    public function test_each_authenticated_video_playback_is_counted_across_sessions(): void
    {
        $user = User::factory()->create();
        $video = $this->videoPayload($user);

        app(CommunityInteractionService::class)->recordVideoView(
            $user,
            'first-session',
            'backstage-post',
            $video['view_key'],
            [],
        );
        $result = app(CommunityInteractionService::class)->recordVideoView(
            $user,
            'second-session',
            'backstage-post',
            $video['view_key'],
            [],
        );

        $this->assertTrue($result['counted']);
        $this->assertSame(2, $result['view_count']);
        $this->assertDatabaseCount('community_video_views', 2);
        $this->assertSame([$user->id, $user->id], CommunityVideoView::query()->pluck('user_id')->all());
    }

    public function test_upgrade_replaces_the_existing_unique_viewer_index_without_losing_views(): void
    {
        Schema::table('community_video_views', function (Blueprint $table) {
            $table->dropIndex('community_video_views_viewer_index');
            $table->unique(['video_key', 'viewer_key'], 'community_video_views_viewer_unique');
        });

        $video = $this->videoPayload();
        app(CommunityInteractionService::class)->recordVideoView(
            null,
            'returning-viewer',
            'backstage-post',
            $video['view_key'],
            [],
        );

        $migration = require database_path('migrations/2026_08_14_203000_allow_repeated_community_video_views.php');
        $migration->up();

        $result = app(CommunityInteractionService::class)->recordVideoView(
            null,
            'returning-viewer',
            'backstage-post',
            $video['view_key'],
            [],
        );

        $this->assertTrue($result['counted']);
        $this->assertSame(2, $result['view_count']);
        $this->assertDatabaseCount('community_video_views', 2);
    }

    public function test_different_guest_sessions_increment_the_same_video_counter(): void
    {
        $community = app(CommunityInteractionService::class);
        $video = $this->videoPayload();

        $community->recordVideoView(null, 'guest-session-one', 'backstage-post', $video['view_key'], []);
        $result = $community->recordVideoView(null, 'guest-session-two', 'backstage-post', $video['view_key'], []);

        $this->assertTrue($result['counted']);
        $this->assertSame(2, $result['view_count']);
    }

    public function test_unknown_video_cannot_create_a_view(): void
    {
        $this->postJson(route('community.posts.videos.views.store', [
            'post' => 'backstage-post',
            'video' => 'video-does-not-exist',
        ]))->assertNotFound();

        $this->assertDatabaseCount('community_video_views', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function videoPayload(?User $user = null): array
    {
        $viewModel = app(CommunityInteractionService::class)->viewModel($user, []);

        return $viewModel['posts'][0]['media_items'][0];
    }
}
