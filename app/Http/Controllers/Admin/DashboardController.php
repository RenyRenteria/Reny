<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccessState;
use App\Http\Controllers\Controller;
use App\Models\AccessEvent;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $yearStart = $now->startOfYear();

        $monthlySalesCents = Order::query()
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->where('currency', 'USD')
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<=', $now)
            ->sum('amount_cents');

        $monthlySales = $monthlySalesCents / 100;

        return view('admin.stats', [
            'stats' => [
                'homepageViews' => $this->monthlyEventCount('page_view', 'page', 'home', $monthStart, $now),
                'paywallViews' => $this->monthlyEventCount('permission_denied', 'access_gate', null, $monthStart, $now),
                'royalMembers' => User::query()
                    ->whereIn('royal_status', [AccessState::RoyalActive->value, AccessState::RoyalGrace->value])
                    ->where('royal_ends_at', '>', $now)
                    ->count(),
                'monthlySales' => $monthlySales,
            ],
            'salesChart' => $this->salesChart($yearStart, $now),
        ]);
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

    /**
     * @return array{
     *     points: array<int, array{month: string, amount: float, compact: string, height: int, is_zero: bool}>,
     *     ticks: array<int, array{value: float, label: string, is_zero: bool}>
     * }
     */
    private function salesChart(CarbonImmutable $yearStart, CarbonImmutable $now): array
    {
        $salesByMonth = Order::query()
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->where('currency', 'USD')
            ->where('created_at', '>=', $yearStart)
            ->where('created_at', '<=', $now)
            ->get(['amount_cents', 'created_at'])
            ->groupBy(fn (Order $order): int => (int) $order->created_at->format('n'))
            ->map(fn ($orders): float => $orders->sum('amount_cents') / 100);

        $maxAmount = max(0, (float) $salesByMonth->max());
        $tickBase = $maxAmount > 0 ? $maxAmount / 5 : 0;

        return [
            'points' => collect(range(1, 12))
                ->map(function (int $month) use ($salesByMonth, $maxAmount): array {
                    $amount = (float) ($salesByMonth[$month] ?? 0);

                    return [
                        'month' => CarbonImmutable::create(null, $month, 1)->format('F'),
                        'amount' => $amount,
                        'compact' => $this->compactMoneyLabel($amount),
                        'height' => $maxAmount > 0 && $amount > 0 ? max(3, (int) round(($amount / $maxAmount) * 100)) : 0,
                        'is_zero' => $amount <= 0,
                    ];
                })
                ->all(),
            'ticks' => collect(range(5, 1))
                ->map(fn (int $index): array => [
                    'value' => $tickBase * $index,
                    'label' => $this->compactMoneyLabel($tickBase * $index),
                    'is_zero' => $tickBase <= 0,
                ])
                ->all(),
        ];
    }

    private function compactMoneyLabel(float $amount): string
    {
        if ($amount <= 0) {
            return '0';
        }

        if ($amount >= 1_000_000_000) {
            return $this->compactNumber($amount / 1_000_000_000).' billion';
        }

        if ($amount >= 1_000_000) {
            return $this->compactNumber($amount / 1_000_000).' million';
        }

        if ($amount >= 1_000) {
            return $this->compactNumber($amount / 1_000).'k';
        }

        return '$'.number_format($amount, 0);
    }

    private function compactNumber(float $value): string
    {
        $rounded = round($value, 1);

        return (string) ($rounded === floor($rounded) ? (int) $rounded : $rounded);
    }
}
