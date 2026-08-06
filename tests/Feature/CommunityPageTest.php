<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Models\CommunityCountryClub;
use App\Models\CommunityCountryClubMessage;
use App\Models\EditorialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CommunityPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_community_page_mounts_approved_mockup_experience(): void
    {
        $response = $this->get('/royals');

        $response->assertOk();
        $response->assertSee('Directo de Reny.');
        $response->assertSee('Posts de Reny');
        $response->assertSee('Live Chat');
        $response->assertSee('Solo Reny publica');
        $response->assertSee('data-community-tab="feed"', false);
        $response->assertSee('data-community-tab="chat"', false);
        $response->assertSee('class="tab is-active"', false);
        $response->assertSee('href="'.route('royals').'"', false);
        $response->assertSee('images/reny-renteria-logo-white.png');
        $response->assertSee('class="stage-lights"', false);
        $response->assertSee('class="community-shell home-shell royals-shell"', false);
        $response->assertSee('<link rel="canonical" href="'.route('royals').'">', false);

        $html = $response->getContent();

        $this->assertSame(2, substr_count($html, 'images/reny-renteria-logo-white.png'));
        $this->assertSame(1, substr_count($html, 'class="community-experience"'));
        $this->assertSame(1, substr_count($html, 'class="community-live-chat-panel"'));
        $this->assertSame(0, substr_count($html, 'class="community-post-card'));
        $this->assertSame(0, substr_count($html, 'class="vote-card"'));
        $this->assertSame(0, substr_count($html, 'class="club-card"'));
        $this->assertStringNotContainsString('Create group', $html);
        $this->assertStringNotContainsString('Country Clubs', $html);
        $this->assertStringNotContainsString('Fan Votes', $html);
        $this->assertStringNotContainsString('Publish post', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('youtube', strtolower($html));
    }

    public function test_existing_tabs_link_to_royals_route(): void
    {
        foreach (['/', '/videos', '/photos'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="'.route('royals').'"', false)
                ->assertDontSee('href="#community"', false);
        }
    }

    public function test_legacy_community_route_keeps_working_with_royals_as_its_canonical_url(): void
    {
        $this->get('/community')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('royals').'">', false)
            ->assertSee('href="'.route('royals').'"', false);
    }

    public function test_community_post_fallbacks_can_be_resolved_from_versioned_config(): void
    {
        config()->set('reny_catalog.community.posts', [
            [
                'key' => 'config-community-post',
                'title' => 'Config community post',
                'time' => 'Tomorrow',
                'body' => 'Configured community fallback body.',
                'full_body' => 'Configured community fallback full body.',
                'image_url' => null,
                'image_alt' => 'Config community image',
                'base_likes' => 12,
                'base_replies' => 3,
            ],
        ]);
        config()->set('reny_catalog.community.poll', [
            'key' => 'config-poll',
            'question' => 'Configured poll question?',
            'options' => [
                ['key' => 'yes', 'label' => 'Yes', 'votes' => 8],
                ['key' => 'soon', 'label' => 'Soon', 'votes' => 4],
            ],
        ]);
        config()->set('reny_catalog.community.clubs', [
            [
                'key' => 'mexico',
                'name' => 'Mexico',
                'flag_label' => 'MX',
                'base_members' => 1200,
                'activity' => 'Testing configured club',
                'messages' => [
                    ['author' => 'Nora', 'text' => 'Configured club message.'],
                ],
            ],
        ]);

        $response = $this->get('/community');

        $response->assertOk();
        $response->assertSee('Config community post');
        $response->assertDontSee('Configured poll question?');
        $response->assertDontSee('Mexico');
        $response->assertDontSee('Studio note from Reny');
        $response->assertDontSee('Dominican Republic');
    }

    public function test_cms_posts_take_precedence_over_configured_fallbacks(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Post->value,
            'title' => 'CMS Priority Community Post',
            'slug' => 'cms-priority-community-post',
            'body' => 'CMS priority community body.',
        ]);
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Poll->value,
            'title' => 'CMS Priority Poll',
            'slug' => 'cms-priority-poll',
            'metadata' => [
                'question' => 'CMS priority poll question?',
                'options' => ['CMS option one', 'CMS option two'],
            ],
        ]);

        $response = $this->get('/community');

        $response->assertOk();
        $response->assertSee('CMS Priority Community Post');
        $response->assertSee('CMS priority poll question?');
        $response->assertSee('CMS option one');
        $response->assertDontSee('Studio note from Reny');
        $response->assertDontSee('Which drop should go first?');
    }

    public function test_country_clubs_stay_available_on_their_dedicated_routes(): void
    {
        CommunityCountryClub::create([
            'key' => 'panama',
            'name' => 'Panama DB Club',
            'flag_label' => 'PX',
            'activity' => 'DB owned activity',
            'status' => 'active',
        ]);

        $response = $this->get(route('community.clubs.show', 'panama'));

        $response->assertOk();
        $response->assertSee('Panama DB Club');
        $response->assertSee('DB owned activity');
        $response->assertDontSee('Sharing radio clips');

        $this->get('/community')
            ->assertOk()
            ->assertDontSee('Panama DB Club');
    }

    public function test_reny_posts_use_the_official_live_chat_host_profile_photo(): void
    {
        $host = User::factory()->create([
            'name' => 'Reny Renteria',
            'email' => config('admin.community_editor_email'),
            'role' => User::ROLE_ARTIST_ADMIN,
            'avatar_path' => 'storage/avatars/reny-renteria.jpg',
        ]);
        $liveChat = CommunityCountryClub::create([
            'key' => 'official-live-chat',
            'name' => 'Live Chat',
            'flag_label' => 'LIVE',
            'activity' => 'Official community live chat',
            'status' => 'active',
        ]);
        CommunityCountryClubMessage::create([
            'community_country_club_id' => $liveChat->id,
            'user_id' => $host->id,
            'body' => 'Mensaje del host.',
            'status' => 'visible',
        ]);
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Post->value,
            'title' => 'Post oficial de Reny',
            'body' => 'Actualización para Royals.',
        ]);

        $response = $this->get('/royals')->assertOk();
        $avatarSource = 'src="'.asset('storage/avatars/reny-renteria.jpg').'"';

        $response
            ->assertSee($avatarSource, false)
            ->assertSee('data-community-post-avatar', false);
        $this->assertSame(2, substr_count($response->getContent(), $avatarSource));
    }

    public function test_empty_or_invalid_community_config_does_not_render_fallback_cards(): void
    {
        config()->set('reny_catalog.community.posts', ['not-a-post']);
        config()->set('reny_catalog.community.poll', [
            'key' => 'invalid-poll',
            'question' => 'Invalid poll',
            'options' => ['not-an-option'],
        ]);
        config()->set('reny_catalog.community.clubs', ['not-a-club']);

        $response = $this->get('/community');

        $response->assertOk();

        $html = $response->getContent();

        $this->assertSame(0, substr_count($html, 'class="community-post-card'));
        $this->assertStringNotContainsString('class="vote-card"', $html);
        $this->assertSame(0, substr_count($html, 'class="club-card"'));

        config()->set('reny_catalog.community.posts', []);
        config()->set('reny_catalog.community.poll', []);
        config()->set('reny_catalog.community.clubs', []);

        $this->get('/community')->assertOk();
    }
}
