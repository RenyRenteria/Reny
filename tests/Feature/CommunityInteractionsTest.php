<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Models\CommunityCountryClubMessage;
use App\Models\EditorialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CommunityInteractionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_community_write_requires_an_active_royal_pass(): void
    {
        $post = EditorialContent::factory()->published()->create([
            'type' => ContentType::Post->value,
            'metadata' => ['comments_enabled' => true],
        ]);
        $postKey = 'cms-post-'.$post->id;

        $this->postJson(route('community.posts.like', $postKey))
            ->assertUnauthorized()
            ->assertJsonPath('login_url', route('login'));

        $this->postJson(route('community.live-chat.messages.store'), ['body' => 'Guest message'])
            ->assertUnauthorized();

        $openUser = User::factory()->create();

        $this->actingAs($openUser)
            ->postJson(route('community.posts.like', $postKey))
            ->assertForbidden()
            ->assertJsonPath('store_url', route('store'));

        $this->actingAs($openUser)
            ->postJson(route('community.posts.replies.store', $postKey), ['body' => 'Cuenta abierta con login.'])
            ->assertForbidden()
            ->assertJsonPath('store_url', route('store'));

        $this->actingAs($openUser)
            ->postJson(route('community.live-chat.messages.store'), ['body' => 'Open message'])
            ->assertForbidden();

        $this->assertDatabaseCount('community_post_reactions', 0);
        $this->assertDatabaseCount('community_post_replies', 0);
    }

    public function test_royal_member_can_like_and_reply_to_posts(): void
    {
        $post = EditorialContent::factory()->published()->create([
            'type' => ContentType::Post->value,
            'title' => 'Studio note from Reny',
            'body' => '<p>Studio update.</p>',
            'metadata' => ['comments_enabled' => true],
        ]);
        $postKey = 'cms-post-'.$post->id;
        $user = User::factory()->royal()->create([
            'name' => 'Royal Fan',
        ]);

        $this->actingAs($user)
            ->postJson(route('community.posts.like', $postKey))
            ->assertOk()
            ->assertJsonPath('liked', true)
            ->assertJsonPath('count', 1);

        $this->assertDatabaseHas('community_post_reactions', [
            'user_id' => $user->id,
            'post_key' => $postKey,
            'reaction' => 'like',
        ]);

        $this->actingAs($user)
            ->postJson(route('community.posts.replies.store', $postKey), [
                'body' => 'Love this studio update.',
            ])
            ->assertCreated()
            ->assertJsonPath('author', 'Royal Fan')
            ->assertJsonPath('reply_count', 1);

        $this->assertDatabaseHas('community_post_replies', [
            'user_id' => $user->id,
            'post_key' => $postKey,
            'body' => 'Love this studio update.',
            'status' => 'visible',
        ]);

        $this->actingAs($user)
            ->get('/community')
            ->assertOk()
            ->assertSee('1 respuestas')
            ->assertSee('Love this studio update.');
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

        $this->assertDatabaseCount('community_poll_votes', 1);
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

    public function test_royal_member_can_send_and_poll_live_chat_messages(): void
    {
        $user = User::factory()->royal()->create([
            'name' => 'Live Fan',
            'avatar_path' => 'storage/avatars/live-fan.jpg',
        ]);

        $this->actingAs($user)
            ->postJson(route('community.live-chat.messages.store'), [
                'body' => 'Hola desde el live chat.',
            ])
            ->assertCreated()
            ->assertJsonPath('chat_message.author', 'Live Fan')
            ->assertJsonPath('chat_message.avatar_url', asset('storage/avatars/live-fan.jpg'))
            ->assertJsonPath('chat_message.text', 'Hola desde el live chat.')
            ->assertJsonPath('chat_message.is_self', true);

        $this->assertDatabaseHas('community_country_clubs', [
            'key' => 'official-live-chat',
        ]);
        $this->assertDatabaseHas('community_country_club_messages', [
            'user_id' => $user->id,
            'body' => 'Hola desde el live chat.',
            'status' => 'visible',
        ]);

        $this->actingAs($user)
            ->getJson(route('community.live-chat.messages.index'))
            ->assertOk()
            ->assertJsonPath('messages.0.avatar_url', asset('storage/avatars/live-fan.jpg'))
            ->assertJsonPath('messages.0.text', 'Hola desde el live chat.')
            ->assertJsonPath('messages.0.is_self', true);

        $this->actingAs($user)
            ->get('/community')
            ->assertOk()
            ->assertSee('src="'.asset('storage/avatars/live-fan.jpg').'"', false)
            ->assertSee('data-live-chat-avatar-image', false);
    }

    public function test_live_chat_uses_initials_when_the_author_has_no_avatar(): void
    {
        $user = User::factory()->royal()->create([
            'name' => 'Royal Reader',
            'avatar_path' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('community.live-chat.messages.store'), ['body' => 'Sin foto.'])
            ->assertCreated()
            ->assertJsonPath('chat_message.initials', 'RR')
            ->assertJsonPath('chat_message.avatar_url', null);

        $response = $this->actingAs($user)->get('/community')->assertOk();

        $response->assertSee('<span>RR</span>', false);
        $this->assertSame(0, substr_count($response->getContent(), 'data-live-chat-avatar-image'));
    }

    public function test_blocking_a_chat_user_hides_their_messages_for_the_blocker_only(): void
    {
        $blocked = User::factory()->royal()->create(['name' => 'Blocked Fan']);
        $blocker = User::factory()->royal()->create(['name' => 'Safe Fan']);

        $this->actingAs($blocked)
            ->postJson(route('community.live-chat.messages.store'), ['body' => 'Message to hide'])
            ->assertCreated();

        $this->actingAs($blocker)
            ->getJson(route('community.live-chat.messages.index'))
            ->assertJsonPath('messages.0.text', 'Message to hide');

        $this->actingAs($blocker)
            ->postJson(route('community.live-chat.users.block', $blocked))
            ->assertOk()
            ->assertJsonPath('blocked_user_id', $blocked->id);

        $this->assertDatabaseHas('community_user_blocks', [
            'blocker_id' => $blocker->id,
            'blocked_id' => $blocked->id,
        ]);

        $this->actingAs($blocker)
            ->getJson(route('community.live-chat.messages.index'))
            ->assertJsonCount(0, 'messages');

        $this->actingAs($blocked)
            ->getJson(route('community.live-chat.messages.index'))
            ->assertJsonPath('messages.0.text', 'Message to hide');
    }

    public function test_moderator_can_hide_a_live_chat_message(): void
    {
        $fan = User::factory()->royal()->create();
        $moderator = User::factory()->create([
            'role' => User::ROLE_MODERATOR,
        ]);

        $response = $this->actingAs($fan)
            ->postJson(route('community.live-chat.messages.store'), ['body' => 'Moderate this'])
            ->assertCreated();
        $message = CommunityCountryClubMessage::findOrFail($response->json('chat_message.id'));

        $this->actingAs($moderator)
            ->deleteJson(route('community.live-chat.messages.moderate', $message))
            ->assertOk()
            ->assertJsonPath('removed_message_id', $message->id);

        $this->assertDatabaseHas('community_country_club_messages', [
            'id' => $message->id,
            'status' => 'removed',
        ]);

        $this->getJson(route('community.live-chat.messages.index'))
            ->assertJsonCount(0, 'messages');
    }

    public function test_live_chat_rate_limit_rejects_bursts(): void
    {
        $user = User::factory()->royal()->create();
        RateLimiter::clear((string) $user->id);

        for ($index = 0; $index < 8; $index++) {
            $this->actingAs($user)
                ->postJson(route('community.live-chat.messages.store'), [
                    'body' => 'Message '.($index + 1),
                ])
                ->assertCreated();
        }

        $this->actingAs($user)
            ->postJson(route('community.live-chat.messages.store'), ['body' => 'One too many'])
            ->assertTooManyRequests();

        RateLimiter::clear((string) $user->id);
    }
}
