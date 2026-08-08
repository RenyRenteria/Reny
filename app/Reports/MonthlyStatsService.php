<?php

namespace App\Reports;

use App\Enums\AccessState;
use App\Models\AccessEvent;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Throwable;

final class MonthlyStatsService
{
    public function __construct(private readonly string $timezone) {}

    /**
     * @return array<string, array{value: int, has_error: bool}>
     */
    public function metrics(): array
    {
        $now = CarbonImmutable::now($this->timezone);
        $monthStart = $now->startOfMonth()->utc();
        $nowUtc = $now->utc();

        return [
            'homepageViews' => $this->resolve(fn (): int => $this->monthlyEventCount(
                'page_view',
                'page',
                'home',
                $monthStart,
                $nowUtc,
            )),
            'paywallViews' => $this->resolve(fn (): int => $this->monthlyEventCount(
                'permission_denied',
                'access_gate',
                null,
                $monthStart,
                $nowUtc,
            )),
            'royalMembers' => $this->resolve(fn (): int => User::query()
                ->whereIn('royal_status', [AccessState::RoyalActive->value, AccessState::RoyalGrace->value])
                ->where('royal_ends_at', '>', $nowUtc)
                ->count()),
            'monthlySales' => $this->resolve(fn (): int => (int) Order::query()
                ->where('status', 'completed')
                ->whereNull('refunded_at')
                ->where('currency', 'USD')
                ->where('created_at', '>=', $monthStart)
                ->where('created_at', '<=', $nowUtc)
                ->sum('amount_cents')),
        ];
    }

    /**
     * @return array{value: int, has_error: bool}
     */
    private function resolve(callable $resolver): array
    {
        try {
            return ['value' => $resolver(), 'has_error' => false];
        } catch (Throwable $exception) {
            report($exception);

            return ['value' => 0, 'has_error' => true];
        }
    }

    private function monthlyEventCount(
        string $eventName,
        string $resourceType,
        ?string $resourceKey,
        CarbonImmutable $monthStart,
        CarbonImmutable $now,
    ): int {
        return AccessEvent::query()
            ->where('event_name', $eventName)
            ->where('resource_type', $resourceType)
            ->when($resourceKey, fn ($query) => $query->where('resource_key', $resourceKey))
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<=', $now)
            ->count();
    }
}
