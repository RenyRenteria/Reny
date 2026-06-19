<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityInteractionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_open_accounts_are_gated_from_community_mutations(): void
    {
        $this->postJson(route('community.posts.like', 'studio-note-from-reny'))
            ->assertUnauthorized()
            ->assertJsonPath('login_url', route('login'));

        $openUser = User::factory()->create();

        $this->actingAs($openUser)
            ->postJson(route('community.posts.like', 'studio-note-from-reny'))
            ->assertForbidden()
            ->assertJsonPath('store_url', route('store'));

        $this->assertDatabaseCount('community_post_reactions', 0);
    }

    public function test_royal_member_can_like_and_reply_to_posts(): void
    {
        $user = User::factory()->royal()->create([
            'name' => 'Royal Fan',
        ]);

        $this->actingAs($user)
            ->postJson(route('community.posts.like', 'studio-note-from-reny'))
            ->assertOk()
            ->assertJsonPath('liked', true)
            ->assertJsonPath('count', 1);

        $this->assertDatabaseHas('community_post_reactions', [
            'user_id' => $user->id,
            'post_key' => 'studio-note-from-reny',
            'reaction' => 'like',
        ]);

        $this->actingAs($user)
            ->postJson(route('community.posts.replies.store', 'studio-note-from-reny'), [
                'body' => 'Love this studio update.',
            ])
            ->assertCreated()
            ->assertJsonPath('author', 'Royal Fan')
            ->assertJsonPath('reply_count', 1);

        $this->assertDatabaseHas('community_post_replies', [
            'user_id' => $user->id,
            'post_key' => 'studio-note-from-reny',
            'body' => 'Love this studio update.',
            'status' => 'visible',
        ]);

        $this->actingAs($user)
            ->get('/community')
            ->assertOk()
            ->assertSee('285')
            ->assertSee('39 replies');
    }

    public function test_poll_vote_persists_and_blocks_duplicate_vote(): void
    {
        $user = User::factory()->royal()->create();

        $this->actingAs($user)
            ->postJson(route('community.polls.vote', 'which-drop-should-go-first'), [
                'option_key' => 'studio-photos',
                'option_label' => 'Studio photos',
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->actingAs($user)
            ->postJson(route('community.polls.vote', 'which-drop-should-go-first'), [
                'option_key' => 'travel-archive',
                'option_label' => 'Travel archive',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'You already voted in this poll.');

        $this->assertDatabaseCount('community_poll_votes', 1);
        $this->assertDatabaseHas('community_poll_votes', [
            'user_id' => $user->id,
            'poll_key' => 'which-drop-should-go-first',
            'option_key' => 'studio-photos',
        ]);

        $this->actingAs($user)
            ->get('/community')
            ->assertOk()
            ->assertSee('Vote saved');
    }

    public function test_country_club_detail_join_and_messages_are_persistent(): void
    {
        $user = User::factory()->royal()->create([
            'name' => 'Panama Fan',
        ]);

        $this->get(route('community.clubs.show', 'panama'))
            ->assertOk()
            ->assertSee('Panama')
            ->assertSee('Sharing radio clips')
            ->assertSee('Sign in to join');

        $this->actingAs($user)
            ->postJson(route('community.clubs.messages.store', 'panama'), [
                'body' => 'Ready for Panama meetup.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('club');

        $this->actingAs($user)
            ->postJson(route('community.clubs.join', 'panama'))
            ->assertOk()
            ->assertJsonPath('joined', true)
            ->assertJsonPath('club.key', 'panama');

        $this->actingAs($user)
            ->postJson(route('community.clubs.join', 'panama'))
            ->assertOk()
            ->assertJsonPath('joined', true);

        $this->assertDatabaseHas('community_country_clubs', [
            'key' => 'panama',
        ]);
        $this->assertDatabaseCount('community_country_club_memberships', 1);

        $this->actingAs($user)
            ->postJson(route('community.clubs.messages.store', 'panama'), [
                'body' => 'Ready for Panama meetup.',
            ])
            ->assertCreated()
            ->assertJsonPath('author', 'Panama Fan')
            ->assertJsonPath('text', 'Ready for Panama meetup.');

        $this->actingAs($user)
            ->get(route('community.clubs.show', 'panama'))
            ->assertOk()
            ->assertSee('Joined')
            ->assertSee('Ready for Panama meetup.');
    }

    public function test_royal_member_can_create_custom_country_club(): void
    {
        $user = User::factory()->royal()->create();

        $this->actingAs($user)
            ->postJson(route('community.clubs.store'), [
                'name' => 'Costa Rica',
                'activity' => 'Organizing the San Jose listening thread',
            ])
            ->assertCreated()
            ->assertJsonPath('club.key', 'costa-rica')
            ->assertJsonPath('club.joined', true);

        $this->assertDatabaseHas('community_country_clubs', [
            'key' => 'costa-rica',
            'name' => 'Costa Rica',
            'created_by_id' => $user->id,
        ]);
        $this->assertDatabaseCount('community_country_club_memberships', 1);
    }
}
