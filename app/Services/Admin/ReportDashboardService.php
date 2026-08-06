<?php

namespace App\Services\Admin;

use App\Enums\AccessState;
use App\Models\AccessEvent;
use App\Models\FanEvent;
use App\Models\Order;
use App\Models\Rsvp;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class ReportDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(ReportPeriod $period, string $productSort = 'sales'): array
    {
        [$commerce, $commerceError] = $this->resolve(
            fn (): array => $this->commerce($period, $productSort),
            $this->emptyCommerce(),
        );
        [$audience, $audienceError] = $this->resolve(
            fn (): array => $this->audience($period),
            $this->emptyAudience(),
        );
        [$funnel, $funnelError] = $this->resolve(
            fn (): array => $this->funnel($period),
            $this->emptyFunnel(),
        );
        [$content, $contentError] = $this->resolve(
            fn (): array => $this->content($period),
            [],
        );
        [$shows, $showsError] = $this->resolve(
            fn (): array => $this->shows($period),
            [],
        );

        return [
            'period' => $period,
            'product_sort' => $productSort,
            'kpis' => [
                'sales' => $commerce['sales'],
                'orders' => $commerce['orders'],
                'royals' => $audience['royals'],
                'users' => $audience['users'],
            ],
            'sales_charts' => $commerce['charts'],
            'commerce_coverage' => $commerce['coverage'],
            'products' => $commerce['products'],
            'funnel' => $funnel,
            'content' => $content,
            'shows' => $shows,
            'module_errors' => [
                'commerce' => $commerceError,
                'audience' => $audienceError,
                'funnel' => $funnelError,
                'content' => $contentError,
                'shows' => $showsError,
            ],
        ];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, int|float|string|null>>}
     */
    public function export(string $report, ReportPeriod $period, string $productSort = 'sales'): array
    {
        if (! in_array($report, ['summary', 'sales', 'funnel', 'products', 'content', 'shows'], true)) {
            throw new RuntimeException('Unknown report export.');
        }

        $dashboard = $this->dashboard($period, $productSort);
        $requiredModules = match ($report) {
            'summary' => ['commerce', 'audience'],
            'sales', 'products' => ['commerce'],
            default => [$report],
        };

        foreach ($requiredModules as $module) {
            if ($dashboard['module_errors'][$module] ?? true) {
                throw new RuntimeException("The {$module} report is currently unavailable.");
            }
        }

        return match ($report) {
            'summary' => $this->summaryExport($dashboard),
            'sales' => $this->salesExport($dashboard),
            'funnel' => $this->funnelExport($dashboard),
            'products' => $this->productsExport($dashboard),
            'content' => $this->contentExport($dashboard),
            'shows' => $this->showsExport($dashboard),
        };
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @param  TValue  $fallback
     * @return array{0: TValue, 1: bool}
     */
    private function resolve(callable $callback, mixed $fallback): array
    {
        try {
            return [$callback(), false];
        } catch (Throwable $exception) {
            report($exception);

            return [$fallback, true];
        }
    }

    /** @return array<string, mixed> */
    private function commerce(ReportPeriod $period, string $productSort): array
    {
        $currentCompleted = $this->completedOrders($period->start, $period->end);
        $currentRefunded = $this->refundedOrders($period->start, $period->end);
        $previousCompleted = $this->completedOrders($period->previousStart, $period->previousEnd);
        $previousRefunded = $this->refundedOrders($period->previousStart, $period->previousEnd);
        $currentSales = $this->netSalesByCurrency($currentCompleted, $currentRefunded);
        $previousSales = $this->netSalesByCurrency($previousCompleted, $previousRefunded);
        $currencies = collect(array_keys($currentSales))
            ->merge(array_keys($previousSales))
            ->whenEmpty(fn (Collection $values): Collection => $values->push('USD'))
            ->unique()
            ->sort()
            ->values();

        $sales = $currencies->map(function (string $currency) use ($currentSales, $previousSales): array {
            $current = (int) ($currentSales[$currency] ?? 0);
            $previous = (int) ($previousSales[$currency] ?? 0);

            return [
                'currency' => $currency,
                'current_cents' => $current,
                'previous_cents' => $previous,
                'current' => $this->money($current, $currency),
                'previous' => $this->money($previous, $currency),
                'variation' => $this->variation($current, $previous, 100),
            ];
        })->all();

        $currentOrders = $this->distinctOrderCount($currentCompleted);
        $previousOrders = $this->distinctOrderCount($previousCompleted);

        return [
            'sales' => $sales,
            'orders' => [
                'current' => $currentOrders,
                'previous' => $previousOrders,
                'variation' => $this->variation($currentOrders, $previousOrders),
            ],
            'charts' => $this->salesCharts(
                $period,
                $currencies,
                $currentCompleted,
                $currentRefunded,
                $previousCompleted,
                $previousRefunded,
            ),
            'products' => $this->productRanking($currentCompleted, $currentRefunded, $productSort),
            'coverage' => [
                'status' => $currentCompleted->merge($previousCompleted)
                    ->contains(fn (Order $order): bool => $order->completed_at === null)
                        ? 'partial'
                        : 'complete',
                'legacy_orders' => $currentCompleted->merge($previousCompleted)->whereNull('completed_at')->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function audience(ReportPeriod $period): array
    {
        $currentUsers = $this->newUserCount($period->start, $period->end);
        $previousUsers = $this->newUserCount($period->previousStart, $period->previousEnd);
        $now = CarbonImmutable::now($period->timezone);
        $royals = User::query()
            ->whereIn('royal_status', [AccessState::RoyalActive->value, AccessState::RoyalGrace->value])
            ->where('royal_ends_at', '>', $now)
            ->count();

        return [
            'royals' => [
                'current' => $royals,
                'previous' => null,
                'variation' => null,
                'historical' => false,
                'note' => 'Current active memberships; historical membership state cannot be reconstructed reliably.',
            ],
            'users' => [
                'current' => $currentUsers,
                'previous' => $previousUsers,
                'variation' => $this->variation($currentUsers, $previousUsers),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function funnel(ReportPeriod $period): array
    {
        $eventNames = [
            'page_view',
            'store_product_opened',
            'store_checkout_started',
            'store_payment_succeeded',
            'store_payment_failed',
        ];
        $events = AccessEvent::query()
            ->whereIn('event_name', $eventNames)
            ->whereBetween('created_at', [$period->start, $period->end])
            ->get(['id', 'event_name', 'resource_key', 'session_key', 'created_at']);

        $visits = $events->filter(fn (AccessEvent $event): bool => $event->event_name === 'store_product_opened'
            || ($event->event_name === 'page_view' && in_array($event->resource_key, ['store', 'store_checkout'], true))
        );
        $checkout = $events->where('event_name', 'store_checkout_started');
        $purchases = $events->where('event_name', 'store_payment_succeeded');
        $failures = $events->where('event_name', 'store_payment_failed');
        $visitStage = $this->funnelStage('Store / product visits', $visits);
        $checkoutStage = $this->funnelStage('Checkout started', $checkout);
        $purchaseStage = $this->funnelStage('Purchase completed', $purchases);
        $checkoutStage['conversion'] = $this->rate($checkoutStage['sessions'], $visitStage['sessions']);
        $purchaseStage['conversion'] = $this->rate($purchaseStage['sessions'], $checkoutStage['sessions']);

        $firstCapturedAt = AccessEvent::query()
            ->whereIn('event_name', ['store_checkout_started', 'store_payment_succeeded', 'store_payment_failed'])
            ->min('created_at');
        $coverageStart = $firstCapturedAt ? CarbonImmutable::parse($firstCapturedAt, $period->timezone) : null;

        return [
            'stages' => [$visitStage, $checkoutStage, $purchaseStage],
            'failures' => $this->funnelStage('Failed payments', $failures),
            'coverage' => [
                'status' => $coverageStart === null
                    ? 'unavailable'
                    : ($coverageStart->greaterThan($period->start) ? 'partial' : 'complete'),
                'from' => $coverageStart?->toIso8601String(),
                'label' => $coverageStart?->setTimezone($period->timezone)->format('M j, Y g:i A T'),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function content(ReportPeriod $period): array
    {
        $events = AccessEvent::query()
            ->whereIn('event_name', ['music_play_started', 'video_play_started'])
            ->whereBetween('created_at', [$period->start, $period->end])
            ->get(['id', 'event_name', 'resource_type', 'resource_key', 'session_key', 'metadata']);

        return $events
            ->groupBy(fn (AccessEvent $event): string => $event->event_name.'|'.($event->resource_key ?: 'unknown'))
            ->map(function (Collection $group): array {
                /** @var AccessEvent $first */
                $first = $group->first();
                $type = $first->event_name === 'music_play_started' ? 'music' : 'video';

                return [
                    'type' => $type,
                    'item_id' => $first->resource_key ?: 'unknown',
                    'title' => (string) data_get($first->metadata, 'item_label', str($first->resource_key ?: 'unknown')->headline()),
                    'metric' => 'plays',
                    'events' => $group->count(),
                    'sessions' => $this->uniqueSessions($group),
                ];
            })
            ->sortByDesc(fn (array $row): int => $row['events'])
            ->take(10)
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function shows(ReportPeriod $period): array
    {
        $freeRsvps = Rsvp::query()
            ->whereBetween('created_at', [$period->start, $period->end])
            ->get(['event_key', 'event_name', 'created_at'])
            ->toBase()
            ->groupBy('event_key');
        $freeRsvpKeys = $freeRsvps->keys()
            ->filter(fn (mixed $eventKey): bool => filled($eventKey))
            ->values()
            ->all();
        $events = FanEvent::query()
            ->with(['tickets.order'])
            ->where(function ($query) use ($freeRsvpKeys, $period): void {
                $query->whereBetween('starts_at', [$period->start, $period->end])
                    ->orWhereHas('tickets', function ($tickets) use ($period): void {
                        $tickets->whereBetween('created_at', [$period->start, $period->end])
                            ->orWhereBetween('purchased_at', [$period->start, $period->end])
                            ->orWhereBetween('checked_in_at', [$period->start, $period->end]);
                    });

                if ($freeRsvpKeys !== []) {
                    $query->orWhereIn('metadata->store_event_key', $freeRsvpKeys);
                }
            })
            ->get();
        $matchedRsvpKeys = collect();

        $rows = $events->map(function (FanEvent $event) use ($freeRsvps, $matchedRsvpKeys, $period): array {
            $eventKey = (string) data_get($event->metadata, 'store_event_key', 'event-'.$event->id);
            $matchedRsvpKeys->push($eventKey);
            $tickets = $event->tickets;
            $ticketRsvps = $tickets->filter(fn (Ticket $ticket): bool => $ticket->rsvp_status === 'confirmed'
                && in_array($ticket->status, ['reserved', 'confirmed', 'checked_in'], true)
                && $this->between($ticket->purchased_at ?? $ticket->created_at, $period->start, $period->end)
            )->count();
            $rsvps = $ticketRsvps + ($freeRsvps->get($eventKey)?->count() ?? 0);
            $soldTickets = $tickets->filter(fn (Ticket $ticket): bool => $ticket->order !== null
                && in_array($ticket->order->status, ['completed', 'refunded'], true)
                && $this->between($this->completionAt($ticket->order), $period->start, $period->end)
            )->count();
            $checkIns = $tickets->filter(fn (Ticket $ticket): bool => $this->between($ticket->checked_in_at, $period->start, $period->end)
            )->count();

            return [
                'key' => $eventKey,
                'title' => $event->title,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'starts_at_label' => $event->starts_at?->setTimezone($event->timezone ?: $period->timezone)->format('M j, Y g:i A T'),
                'timezone' => $event->timezone ?: $period->timezone,
                'rsvps' => $rsvps,
                'tickets' => $soldTickets,
                'check_ins' => $checkIns,
                'rsvp_to_ticket' => $this->rate($soldTickets, $rsvps),
                'ticket_to_check_in' => $this->rate($checkIns, $soldTickets),
                'check_in_available' => true,
            ];
        });

        $freeRsvps
            ->except($matchedRsvpKeys->all())
            ->each(function (Collection $group, string $eventKey) use ($rows, $period): void {
                /** @var Rsvp $first */
                $first = $group->first();
                $rows->push([
                    'key' => $eventKey,
                    'title' => $first->event_name,
                    'starts_at' => null,
                    'starts_at_label' => 'Date not connected',
                    'timezone' => $period->timezone,
                    'rsvps' => $group->count(),
                    'tickets' => null,
                    'check_ins' => null,
                    'rsvp_to_ticket' => null,
                    'ticket_to_check_in' => null,
                    'check_in_available' => false,
                ]);
            });

        return $rows
            ->sortBy(fn (array $row): string => $row['starts_at'] ?? '9999-12-31')
            ->values()
            ->all();
    }

    /** @return Collection<int, Order> */
    private function completedOrders(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Order::query()
            ->whereIn('status', ['completed', 'refunded'])
            ->where(function ($query) use ($end, $start): void {
                $query->whereBetween('completed_at', [$start, $end])
                    ->orWhere(function ($legacy) use ($end, $start): void {
                        $legacy->whereNull('completed_at')->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->get([
                'id', 'provider', 'provider_order_id', 'provider_capture_id', 'product_key',
                'amount_cents', 'currency', 'status', 'completed_at', 'refunded_at', 'metadata', 'created_at',
            ]);
    }

    /** @return Collection<int, Order> */
    private function refundedOrders(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Order::query()
            ->whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$start, $end])
            ->get([
                'id', 'provider', 'provider_order_id', 'provider_capture_id', 'product_key',
                'amount_cents', 'currency', 'status', 'completed_at', 'refunded_at', 'metadata', 'created_at',
            ]);
    }

    /**
     * @param  Collection<int, Order>  $completed
     * @param  Collection<int, Order>  $refunded
     * @return array<string, int>
     */
    private function netSalesByCurrency(Collection $completed, Collection $refunded): array
    {
        $totals = [];

        foreach ($completed as $order) {
            $currency = strtoupper($order->currency);
            $totals[$currency] = ($totals[$currency] ?? 0) + (int) $order->amount_cents;
        }

        foreach ($refunded as $order) {
            $currency = strtoupper($order->currency);
            $totals[$currency] = ($totals[$currency] ?? 0) - (int) $order->amount_cents;
        }

        ksort($totals);

        return $totals;
    }

    /**
     * @param  Collection<int, string>  $currencies
     * @param  Collection<int, Order>  $currentCompleted
     * @param  Collection<int, Order>  $currentRefunded
     * @param  Collection<int, Order>  $previousCompleted
     * @param  Collection<int, Order>  $previousRefunded
     * @return array<int, array<string, mixed>>
     */
    private function salesCharts(
        ReportPeriod $period,
        Collection $currencies,
        Collection $currentCompleted,
        Collection $currentRefunded,
        Collection $previousCompleted,
        Collection $previousRefunded,
    ): array {
        $currentBuckets = $this->buckets($period->start, $period->end, $period->isDaily());
        $previousBuckets = $this->alignedPreviousBuckets($period, $currentBuckets);
        $currentTotals = $this->bucketTotals($currentCompleted, $currentRefunded, $currentBuckets);
        $previousTotals = $this->bucketTotals($previousCompleted, $previousRefunded, $previousBuckets);
        $pointCount = count($currentBuckets);

        return $currencies->map(function (string $currency) use (
            $currentBuckets,
            $currentTotals,
            $period,
            $pointCount,
            $previousBuckets,
            $previousTotals,
        ): array {
            $points = [];
            $max = 0;

            for ($index = 0; $index < $pointCount; $index++) {
                $currentBucket = $currentBuckets[$index] ?? null;
                $previousBucket = $previousBuckets[$index] ?? null;
                $current = (int) ($currentTotals[$currency][$currentBucket['key'] ?? ''] ?? 0);
                $previous = (int) ($previousTotals[$currency][$previousBucket['key'] ?? ''] ?? 0);
                $max = max($max, abs($current), abs($previous));
                $points[] = [
                    'label' => $currentBucket['label'],
                    'current_start' => $currentBucket['start']->toIso8601String(),
                    'current_end' => $currentBucket['end']->toIso8601String(),
                    'previous_start' => $previousBucket['start']->toIso8601String(),
                    'previous_end' => $previousBucket['end']->toIso8601String(),
                    'current_range' => $currentBucket['range'],
                    'previous_range' => $previousBucket['range'],
                    'current_cents' => $current,
                    'previous_cents' => $previous,
                    'current' => $this->money($current, $currency),
                    'previous' => $this->money($previous, $currency),
                ];
            }

            $points = collect($points)->map(function (array $point) use ($max): array {
                $point['current_height'] = $this->barHeight($point['current_cents'], $max);
                $point['previous_height'] = $this->barHeight($point['previous_cents'], $max);

                return $point;
            })->all();

            return [
                'currency' => $currency,
                'maximum_cents' => $max,
                'maximum' => $this->money($max, $currency),
                'granularity' => $period->isDaily() ? 'daily' : 'monthly',
                'points' => $points,
            ];
        })->all();
    }

    /**
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable, key: string, label: string, range: string}>
     */
    private function buckets(CarbonImmutable $start, CarbonImmutable $end, bool $daily): array
    {
        $buckets = [];
        $cursor = $daily ? $start->startOfDay() : $start->startOfMonth();

        while ($cursor->lessThanOrEqualTo($end)) {
            $bucketStart = $cursor->max($start);
            $bucketEnd = ($daily ? $cursor->endOfDay() : $cursor->endOfMonth())->min($end);
            $buckets[] = [
                'start' => $bucketStart,
                'end' => $bucketEnd,
                'key' => $daily ? $cursor->format('Y-m-d') : $cursor->format('Y-m'),
                'label' => $daily ? $cursor->format('M j') : $cursor->format('M Y'),
                'range' => $bucketStart->format('M j, Y').' – '.$bucketEnd->format('M j, Y'),
            ];
            $cursor = $daily ? $cursor->addDay() : $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return $buckets;
    }

    /**
     * Keep comparison points aligned to the active period's bucket boundaries. Calendar
     * months have different lengths, so building both ranges independently can create an
     * extra comparison point with no active date.
     *
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable, key: string, label: string, range: string}>  $currentBuckets
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable, key: string, label: string, range: string}>
     */
    private function alignedPreviousBuckets(ReportPeriod $period, array $currentBuckets): array
    {
        return collect($currentBuckets)
            ->map(function (array $bucket) use ($period): array {
                $startOffset = (int) $period->start->diffInMicroseconds($bucket['start']);
                $endOffset = (int) $period->start->diffInMicroseconds($bucket['end']);
                $bucketStart = $period->previousStart->addMicroseconds($startOffset);
                $bucketEnd = $period->previousStart->addMicroseconds($endOffset);

                return [
                    'start' => $bucketStart,
                    'end' => $bucketEnd,
                    'key' => $bucket['key'],
                    'label' => $bucketStart->format('M j'),
                    'range' => $bucketStart->format('M j, Y').' – '.$bucketEnd->format('M j, Y'),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, Order>  $completed
     * @param  Collection<int, Order>  $refunded
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable, key: string, label: string, range: string}>  $buckets
     * @return array<string, array<string, int>>
     */
    private function bucketTotals(Collection $completed, Collection $refunded, array $buckets): array
    {
        $totals = [];

        foreach ($completed as $order) {
            $currency = strtoupper($order->currency);
            $key = $this->bucketKey($this->completionAt($order), $buckets);

            if ($key === null) {
                continue;
            }

            $totals[$currency][$key] = ($totals[$currency][$key] ?? 0) + (int) $order->amount_cents;
        }

        foreach ($refunded as $order) {
            $currency = strtoupper($order->currency);
            $key = $this->bucketKey(CarbonImmutable::instance($order->refunded_at), $buckets);

            if ($key === null) {
                continue;
            }

            $totals[$currency][$key] = ($totals[$currency][$key] ?? 0) - (int) $order->amount_cents;
        }

        return $totals;
    }

    /**
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable, key: string, label: string, range: string}>  $buckets
     */
    private function bucketKey(CarbonImmutable $date, array $buckets): ?string
    {
        foreach ($buckets as $bucket) {
            if ($date->betweenIncluded($bucket['start'], $bucket['end'])) {
                return $bucket['key'];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Order>  $completed
     * @param  Collection<int, Order>  $refunded
     * @return array<int, array<string, mixed>>
     */
    private function productRanking(Collection $completed, Collection $refunded, string $sort): array
    {
        $rows = [];

        foreach ($completed as $order) {
            $key = $order->product_key.'|'.strtoupper($order->currency);
            $rows[$key] ??= $this->emptyProductRow($order);
            $rows[$key]['net_cents'] += (int) $order->amount_cents;
            $rows[$key]['units']++;
            $rows[$key]['order_keys'][$this->orderKey($order)] = true;
        }

        foreach ($refunded as $order) {
            $key = $order->product_key.'|'.strtoupper($order->currency);
            $rows[$key] ??= $this->emptyProductRow($order);
            $rows[$key]['net_cents'] -= (int) $order->amount_cents;
            $rows[$key]['refunds']++;
        }

        $rows = collect($rows)->map(function (array $row): array {
            $row['orders'] = count($row['order_keys']);
            unset($row['order_keys']);
            $row['net_sales'] = $this->money($row['net_cents'], $row['currency']);

            return $row;
        });

        return ($sort === 'units' ? $rows->sortByDesc('units') : $rows->sortByDesc('net_cents'))
            ->take(10)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function emptyProductRow(Order $order): array
    {
        return [
            'key' => $order->product_key,
            'title' => (string) data_get($order->metadata, 'product.title', str($order->product_key)->headline()),
            'kind' => (string) data_get($order->metadata, 'product.kind', 'product'),
            'currency' => strtoupper($order->currency),
            'net_cents' => 0,
            'units' => 0,
            'refunds' => 0,
            'order_keys' => [],
        ];
    }

    private function newUserCount(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return User::query()
            ->where('role', User::ROLE_FAN)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function distinctOrderCount(Collection $orders): int
    {
        return $orders->map(fn (Order $order): string => $this->orderKey($order))->unique()->count();
    }

    private function orderKey(Order $order): string
    {
        return filled($order->provider_capture_id)
            ? $order->provider.'|'.$order->provider_capture_id
            : $order->provider.'|'.$order->provider_order_id;
    }

    private function completionAt(Order $order): CarbonImmutable
    {
        return CarbonImmutable::instance($order->completed_at ?? $order->created_at);
    }

    private function between(mixed $value, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        if ($value === null) {
            return false;
        }

        $date = $value instanceof CarbonImmutable ? $value : CarbonImmutable::instance($value);

        return $date->betweenIncluded($start, $end);
    }

    /**
     * @param  Collection<int, AccessEvent>  $events
     * @return array<string, int|string|null>
     */
    private function funnelStage(string $label, Collection $events): array
    {
        return [
            'label' => $label,
            'sessions' => $this->uniqueSessions($events),
            'events' => $events->count(),
            'conversion' => null,
        ];
    }

    /** @param Collection<int, AccessEvent> $events */
    private function uniqueSessions(Collection $events): int
    {
        return $events->pluck('session_key')->filter()->unique()->count();
    }

    /** @return array{value: float, label: string}|null */
    private function rate(int $numerator, int $denominator): ?array
    {
        if ($denominator <= 0) {
            return null;
        }

        $value = round(($numerator / $denominator) * 100, 1);

        return ['value' => $value, 'label' => $this->number($value).'%'];
    }

    /** @return array{absolute: int|float, absolute_label: string, percentage: float|null, percentage_label: string} */
    private function variation(int|float $current, int|float $previous, int $divisor = 1): array
    {
        $absolute = $current - $previous;
        $percentage = $previous == 0 ? null : round(($absolute / abs($previous)) * 100, 1);

        return [
            'absolute' => $absolute,
            'absolute_label' => ($absolute > 0 ? '+' : '').$this->number($absolute / $divisor),
            'percentage' => $percentage,
            'percentage_label' => $percentage === null ? 'N/A' : (($percentage > 0 ? '+' : '').$this->number($percentage).'%'),
        ];
    }

    private function barHeight(int $value, int $max): int
    {
        return $max > 0 && $value !== 0 ? max(3, (int) round((abs($value) / $max) * 100)) : 0;
    }

    private function money(int $cents, string $currency): string
    {
        return strtoupper($currency).' '.number_format($cents / 100, 2, '.', ',');
    }

    private function number(int|float $value): string
    {
        $rounded = round($value, 2);

        return number_format($rounded, $rounded === floor($rounded) ? 0 : 2, '.', ',');
    }

    /** @return array<string, mixed> */
    private function emptyCommerce(): array
    {
        return [
            'sales' => [[
                'currency' => 'USD',
                'current_cents' => 0,
                'previous_cents' => 0,
                'current' => 'N/A',
                'previous' => 'N/A',
                'variation' => null,
            ]],
            'orders' => ['current' => null, 'previous' => null, 'variation' => null],
            'charts' => [],
            'products' => [],
            'coverage' => ['status' => 'unavailable', 'legacy_orders' => null],
        ];
    }

    /** @return array<string, mixed> */
    private function emptyAudience(): array
    {
        return [
            'royals' => ['current' => null, 'previous' => null, 'variation' => null, 'historical' => false, 'note' => null],
            'users' => ['current' => null, 'previous' => null, 'variation' => null],
        ];
    }

    /** @return array<string, mixed> */
    private function emptyFunnel(): array
    {
        return [
            'stages' => [],
            'failures' => ['label' => 'Failed payments', 'sessions' => null, 'events' => null, 'conversion' => null],
            'coverage' => ['status' => 'unavailable', 'from' => null, 'label' => null],
        ];
    }

    /** @param array<string, mixed> $dashboard */
    private function summaryExport(array $dashboard): array
    {
        $rows = [];

        foreach ($dashboard['kpis']['sales'] as $sales) {
            $rows[] = [
                'net_sales', $sales['currency'], $this->decimalMoney($sales['current_cents']), $this->decimalMoney($sales['previous_cents']),
                $this->decimalMoney((int) ($sales['variation']['absolute'] ?? 0)), $sales['variation']['percentage'] ?? null, 'major_currency_units',
            ];
        }

        foreach (['orders' => 'count', 'users' => 'count', 'royals' => 'current_snapshot'] as $key => $unit) {
            $kpi = $dashboard['kpis'][$key];
            $rows[] = [
                $key === 'users' ? 'new_users' : ($key === 'royals' ? 'active_royals' : 'completed_orders'),
                null,
                $kpi['current'],
                $kpi['previous'],
                $kpi['variation']['absolute'] ?? null,
                $kpi['variation']['percentage'] ?? null,
                $unit,
            ];
        }

        return [
            'headers' => ['metric', 'currency', 'current_value', 'previous_value', 'absolute_change', 'percentage_change', 'unit'],
            'rows' => $rows,
        ];
    }

    /** @param array<string, mixed> $dashboard */
    private function salesExport(array $dashboard): array
    {
        $rows = [];

        foreach ($dashboard['sales_charts'] as $chart) {
            foreach ($chart['points'] as $point) {
                $rows[] = [
                    $chart['currency'], $chart['granularity'], $point['current_start'], $point['current_end'],
                    $this->decimalMoney($point['current_cents']), $point['previous_start'], $point['previous_end'],
                    $this->decimalMoney($point['previous_cents']),
                ];
            }
        }

        return [
            'headers' => [
                'currency', 'granularity', 'current_period_start', 'current_period_end', 'current_net_sales_amount',
                'previous_period_start', 'previous_period_end', 'previous_net_sales_amount',
            ],
            'rows' => $rows,
        ];
    }

    /** @param array<string, mixed> $dashboard */
    private function funnelExport(array $dashboard): array
    {
        $rows = collect($dashboard['funnel']['stages'])
            ->push($dashboard['funnel']['failures'])
            ->map(fn (array $stage): array => [
                str($stage['label'])->snake()->toString(),
                $stage['sessions'],
                $stage['events'],
                $stage['conversion']['value'] ?? null,
                $dashboard['funnel']['coverage']['from'],
                $dashboard['funnel']['coverage']['status'],
            ])->all();

        return [
            'headers' => ['stage', 'unique_sessions', 'total_events', 'conversion_from_previous_percent', 'data_available_from', 'coverage_status'],
            'rows' => $rows,
        ];
    }

    /** @param array<string, mixed> $dashboard */
    private function productsExport(array $dashboard): array
    {
        return [
            'headers' => ['product_key', 'product_title', 'product_type', 'currency', 'net_sales_amount', 'units_sold', 'completed_orders', 'refunds'],
            'rows' => collect($dashboard['products'])->map(fn (array $row): array => [
                $row['key'], $row['title'], $row['kind'], $row['currency'], $this->decimalMoney($row['net_cents']),
                $row['units'], $row['orders'], $row['refunds'],
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $dashboard */
    private function contentExport(array $dashboard): array
    {
        return [
            'headers' => ['content_type', 'content_id', 'title', 'metric', 'total_interactions', 'unique_sessions'],
            'rows' => collect($dashboard['content'])->map(fn (array $row): array => [
                $row['type'], $row['item_id'], $row['title'], $row['metric'], $row['events'], $row['sessions'],
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $dashboard */
    private function showsExport(array $dashboard): array
    {
        return [
            'headers' => ['show_key', 'show_title', 'starts_at', 'timezone', 'confirmed_rsvps', 'tickets_sold', 'check_ins', 'rsvp_to_ticket_percent', 'ticket_to_check_in_percent', 'check_in_available'],
            'rows' => collect($dashboard['shows'])->map(fn (array $row): array => [
                $row['key'], $row['title'], $row['starts_at'], $row['timezone'], $row['rsvps'], $row['tickets'],
                $row['check_ins'], $row['rsvp_to_ticket']['value'] ?? null, $row['ticket_to_check_in']['value'] ?? null,
                $row['check_in_available'] ? 'yes' : 'no',
            ])->all(),
        ];
    }

    private function decimalMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
