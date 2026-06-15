<?php

namespace Tests\Feature;

use App\Models\PointLedgerEntry;
use App\Models\User;
use App\Services\PointLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointsLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_point_ledger_service_posts_activity_once_by_idempotency_key(): void
    {
        $user = User::factory()->create();
        $points = app(PointLedgerService::class);

        $first = $points->post(
            user: $user,
            eventType: 'comment_posted',
            delta: 10,
            idempotencyKey: 'comment_posted:user-'.$user->id.':comment-1',
            sourceType: 'comment',
            sourceId: 'comment-1',
        );
        $second = $points->post(
            user: $user,
            eventType: 'comment_posted',
            delta: 10,
            idempotencyKey: 'comment_posted:user-'.$user->id.':comment-1',
            sourceType: 'comment',
            sourceId: 'comment-1',
        );

        $this->assertTrue($first->is($second));
        $this->assertSame(10, $points->balance($user));
        $this->assertDatabaseCount('point_ledger_entries', 1);
    }

    public function test_points_leaderboard_uses_only_posted_entries(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Fan', 'username' => 'alice']);
        $bob = User::factory()->create(['name' => 'Bob Fan', 'username' => 'bob']);
        $points = app(PointLedgerService::class);

        $points->post($alice, 'poll_answered', 7, 'poll:user-'.$alice->id.':poll-1', 'poll', 'poll-1');
        $points->post($bob, 'video_watched', 20, 'video:user-'.$bob->id.':video-1', 'video', 'video-1');
        PointLedgerEntry::create([
            'user_id' => $alice->id,
            'event_type' => 'purchase_pending',
            'source_type' => 'order',
            'source_id' => 'ORDER-PENDING',
            'delta' => 999,
            'status' => 'pending',
            'balance_after' => 1006,
            'idempotency_key' => 'pending:user-'.$alice->id.':order-1',
            'actor_type' => 'system',
        ]);

        $this->actingAs($alice)
            ->get('/points')
            ->assertOk()
            ->assertSeeInOrder(['Leaderboard', '@bob', '20', '@alice', '7'])
            ->assertDontSee('999');
    }

    public function test_points_route_is_read_only_for_clients(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/points', [
                'delta' => 1000,
            ])
            ->assertMethodNotAllowed();

        $this->assertDatabaseCount('point_ledger_entries', 0);
    }
}
