<?php

namespace App\Services;

use App\Models\PointLedgerEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PointLedgerService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function post(
        User $user,
        string $eventType,
        int $delta,
        string $idempotencyKey,
        ?string $sourceType = null,
        ?string $sourceId = null,
        string $actorType = 'system',
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = [],
    ): PointLedgerEntry {
        if ($delta === 0) {
            throw new \InvalidArgumentException('Point delta must not be zero.');
        }

        return DB::transaction(function () use (
            $actor,
            $actorType,
            $delta,
            $eventType,
            $idempotencyKey,
            $metadata,
            $reason,
            $sourceId,
            $sourceType,
            $user
        ) {
            $existing = PointLedgerEntry::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $balance = $this->balance($user);

            return PointLedgerEntry::create([
                'user_id' => $user->id,
                'event_type' => $eventType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'delta' => $delta,
                'status' => 'posted',
                'balance_after' => $balance + $delta,
                'idempotency_key' => $idempotencyKey,
                'posted_at' => now(),
                'actor_type' => $actorType,
                'actor_id' => $actor?->id,
                'reason' => $reason,
                'metadata' => $metadata ?: null,
            ]);
        });
    }

    public function balance(User $user): int
    {
        return (int) PointLedgerEntry::query()
            ->where('user_id', $user->id)
            ->where('status', 'posted')
            ->sum('delta');
    }

    /**
     * @return Collection<int, PointLedgerEntry>
     */
    public function leaderboard(int $limit = 10): Collection
    {
        return PointLedgerEntry::query()
            ->select('user_id')
            ->selectRaw('SUM(delta) as points')
            ->where('status', 'posted')
            ->with('user:id,name,username')
            ->groupBy('user_id')
            ->orderByDesc('points')
            ->take($limit)
            ->get();
    }
}
