<?php

namespace App\Reports;

use App\Enums\AccessState;
use App\Models\AccessEvent;
use App\Models\FanEvent;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Rsvp;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

final class DashboardReportService
{
    private const COMPLETED_ORDER_STATUSES = ['completed', 'refunded'];

    private const FUNNEL_EVENT_NAMES = [
        'page_view',
        'store_product_opened',
        'store_checkout_started',
        'store_payment_failed',
    ];

    private const CONTENT_EVENT_NAMES = [
        'music_play_started',
        'video_play_started',
        'photo_opened',
        'community_note_opened',
    ];

    /** @var array<string, Collection<int, Order>> */
    private array $capturedOrderCache = [];

    /** @var array<string, Collection<int, OrderRefund>> */
    private array $refundCache = [];

    /** @var array<string, Collection<int, AccessEvent>> */
    private array $eventCache = [];

    /** @var Collection<int, AccessEvent>|null */
    private ?Collection $comparisonPageViews = null;

    /** @var array{visitor_available_from: string|null, traffic_available_from: string|null}|null */
    private ?array $analyticsCoverage = null;

    public function __construct(private readonly ReportRange $range) {}

    /**
     * @return array<string, array{status: string, data: mixed, message: string|null}>
     */
    public function modules(string $productSort = 'sales'): array
    {
        return [
            'commerce_coverage' => $this->resolve(fn (): array => $this->commerceCoverage()),
            'kpis' => $this->resolve(fn (): array => $this->kpis()),
            'audience' => $this->resolve(fn (): array => $this->audience()),
            'acquisition' => $this->resolve(fn (): array => $this->acquisition()),
            'sales' => $this->resolve(fn (): array => $this->salesSeries()),
            'funnel' => $this->resolve(fn (): array => $this->funnel()),
            'products' => $this->resolve(fn (): array => $this->products($productSort)),
            'content' => $this->resolve(fn (): array => $this->content()),
            'shows' => $this->resolve(fn (): array => $this->shows()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function kpis(): array
    {
        $currentCaptures = $this->capturedOrders($this->range->startUtc(), $this->range->endExclusiveUtc());
        $previousCaptures = $this->capturedOrders($this->range->previousStartUtc(), $this->range->previousEndExclusiveUtc());
        $currentRefunds = $this->refunds($this->range->startUtc(), $this->range->endExclusiveUtc());
        $previousRefunds = $this->refunds($this->range->previousStartUtc(), $this->range->previousEndExclusiveUtc());

        $currentSales = $this->netSalesByCurrency($currentCaptures, $currentRefunds);
        $previousSales = $this->netSalesByCurrency($previousCaptures, $previousRefunds);
        $currencies = collect([...$currentSales->keys(), ...$previousSales->keys()])->unique()->sort()->values();

        if ($currencies->isEmpty()) {
            $currencies->push('USD');
        }

        $sales = $currencies->map(function (string $currency) use ($currentSales, $previousSales): array {
            return [
                'currency' => $currency,
                ...$this->comparison(
                    (int) $currentSales->get($currency, 0),
                    (int) $previousSales->get($currency, 0),
                ),
            ];
        })->all();

        $currentOrders = $currentCaptures->map($this->transactionKey(...))->unique()->count();
        $previousOrders = $previousCaptures->map($this->transactionKey(...))->unique()->count();

        $currentUsers = $this->newUsers($this->range->startUtc(), $this->range->endExclusiveUtc());
        $previousUsers = $this->newUsers($this->range->previousStartUtc(), $this->range->previousEndExclusiveUtc());
        $now = CarbonImmutable::now($this->range->timezone)->utc();
        $royals = User::query()
            ->whereIn('royal_status', [AccessState::RoyalActive->value, AccessState::RoyalGrace->value])
            ->where('royal_ends_at', '>', $now)
            ->count();

        return [
            'sales' => $sales,
            'orders' => $this->comparison($currentOrders, $previousOrders),
            'royals' => [
                'current' => $royals,
                'previous' => null,
                'absolute' => null,
                'percent' => null,
                'is_current_snapshot' => true,
            ],
            'users' => $this->comparison($currentUsers, $previousUsers),
            'definitions' => [
                'sales' => 'Pagos con fecha de captura canónica en el rango menos reembolsos registrados en el rango, separados por moneda.',
                'orders' => 'Transacciones distintas con fecha de captura canónica en el rango. Un reembolso posterior no borra la conversión.',
                'royals' => 'Membresías activas o en gracia al momento actual. El modelo no conserva snapshots históricos confiables.',
                'users' => 'Cuentas fan creadas en el rango; excluye cuentas con roles administrativos o de moderación.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function audience(): array
    {
        $currentEvents = $this->humanPageViews(
            $this->range->startUtc(),
            $this->range->endExclusiveUtc(),
        );
        $previousEvents = $this->humanPageViews(
            $this->range->previousStartUtc(),
            $this->range->previousEndExclusiveUtc(),
        );
        $current = $this->audiencePeriod($currentEvents, $this->range->startUtc());
        $previous = $this->audiencePeriod($previousEvents, $this->range->previousStartUtc());
        $availableFrom = $this->analyticsCoverage()['visitor_available_from'];
        $availableDate = $availableFrom
            ? CarbonImmutable::parse($availableFrom, 'UTC')->setTimezone($this->range->timezone)->toDateString()
            : null;
        $currentCoverage = $this->analyticsPeriodCoverage(
            $availableDate,
            $this->range->startDate(),
            $this->range->endDate(),
            $current['identified_page_views'],
            $current['observed_page_views'],
        );
        $previousCoverage = $this->analyticsPeriodCoverage(
            $availableDate,
            $this->range->previousStartLocal->toDateString(),
            $this->range->previousEndExclusiveLocal->subDay()->toDateString(),
            $previous['identified_page_views'],
            $previous['observed_page_views'],
        );
        $comparisonAvailable = $currentCoverage === 'complete' && $previousCoverage === 'complete';

        return [
            'visitors' => $this->comparison($current['visitors'], $previous['visitors'], $comparisonAvailable),
            'sessions' => $this->comparison($current['sessions'], $previous['sessions'], $comparisonAvailable),
            'page_views' => $this->comparison($current['page_views'], $previous['page_views'], $comparisonAvailable),
            'new_visitors' => $this->comparison($current['new_visitors'], $previous['new_visitors'], $comparisonAvailable),
            'returning_visitors' => $this->comparison($current['returning_visitors'], $previous['returning_visitors'], $comparisonAvailable),
            'identified_page_view_percent' => $this->rate($current['identified_page_views'], $current['observed_page_views']),
            'available_from' => $availableDate,
            'coverage_unavailable' => $currentCoverage === 'unavailable',
            'coverage_partial' => $currentCoverage !== 'complete' || $previousCoverage !== 'complete',
            'current_coverage_status' => $currentCoverage,
            'previous_coverage_status' => $previousCoverage,
            'comparison_available' => $comparisonAvailable,
            'definitions' => [
                'visitors' => 'Navegadores anónimos distintos. El identificador no contiene nombre, email, IP ni otros datos personales.',
                'sessions' => 'Sesiones distintas con hasta 30 minutos de inactividad entre eventos. Una misma persona puede iniciar varias sesiones.',
                'page_views' => 'Cargas identificadas de páginas públicas, incluidas navegaciones internas de la SPA. Se excluyen el CMS, eventos históricos sin identidad y agentes identificados como bots.',
                'new_returning' => 'Nuevo significa que no existe una visita anterior con el mismo identificador anónimo; recurrente significa que sí existe.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function acquisition(): array
    {
        $current = $this->humanPageViews($this->range->startUtc(), $this->range->endExclusiveUtc());
        $previous = $this->humanPageViews($this->range->previousStartUtc(), $this->range->previousEndExclusiveUtc());
        $currentAttributed = $current->filter(
            fn (AccessEvent $event): bool => $event->traffic_source !== null,
        )->values();
        $previousAttributed = $previous->filter(
            fn (AccessEvent $event): bool => $event->traffic_source !== null,
        )->values();
        $availableFrom = $this->analyticsCoverage()['traffic_available_from'];
        $availableDate = $availableFrom
            ? CarbonImmutable::parse($availableFrom, 'UTC')->setTimezone($this->range->timezone)->toDateString()
            : null;
        $attributedPageViews = $currentAttributed->count();
        $currentCoverage = $this->analyticsPeriodCoverage(
            $availableDate,
            $this->range->startDate(),
            $this->range->endDate(),
            $attributedPageViews,
            $current->count(),
        );
        $previousCoverage = $this->analyticsPeriodCoverage(
            $availableDate,
            $this->range->previousStartLocal->toDateString(),
            $this->range->previousEndExclusiveLocal->subDay()->toDateString(),
            $previousAttributed->count(),
            $previous->count(),
        );
        $comparisonAvailable = $currentCoverage === 'complete' && $previousCoverage === 'complete';

        return [
            'channels' => $this->dimensionRows($currentAttributed, $previousAttributed, [
                'traffic_source',
                'traffic_medium',
                'traffic_campaign',
            ], $comparisonAvailable),
            'devices' => $this->dimensionRows($currentAttributed, $previousAttributed, ['device_category'], $comparisonAvailable),
            'countries' => $this->dimensionRows($currentAttributed, $previousAttributed, ['country_code'], $comparisonAvailable),
            'attributed_page_view_percent' => $this->rate($attributedPageViews, $current->count()),
            'available_from' => $availableDate,
            'coverage_unavailable' => $currentCoverage === 'unavailable',
            'coverage_partial' => $currentCoverage !== 'complete' || $previousCoverage !== 'complete',
            'current_coverage_status' => $currentCoverage,
            'previous_coverage_status' => $previousCoverage,
            'comparison_available' => $comparisonAvailable,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function salesSeries(): array
    {
        $currentSlots = $this->slots($this->range->startLocal, $this->range->endExclusiveLocal);
        $previousSlots = $this->slots($this->range->previousStartLocal, $this->range->previousEndExclusiveLocal);
        $current = $this->netSalesByBucket(
            $this->capturedOrders($this->range->startUtc(), $this->range->endExclusiveUtc()),
            $this->refunds($this->range->startUtc(), $this->range->endExclusiveUtc()),
            $this->range->startLocal,
        );
        $previous = $this->netSalesByBucket(
            $this->capturedOrders($this->range->previousStartUtc(), $this->range->previousEndExclusiveUtc()),
            $this->refunds($this->range->previousStartUtc(), $this->range->previousEndExclusiveUtc()),
            $this->range->previousStartLocal,
        );
        $currencies = collect([...$current->keys(), ...$previous->keys()])->unique()->sort()->values();

        if ($currencies->isEmpty()) {
            $currencies->push('USD');
        }

        $series = $currencies->map(function (string $currency) use ($current, $currentSlots, $previous, $previousSlots): array {
            $currentValues = $current->get($currency, collect());
            $previousValues = $previous->get($currency, collect());
            $pointCount = max($currentSlots->count(), $previousSlots->count());

            return [
                'currency' => $currency,
                'points' => collect(range(0, max(0, $pointCount - 1)))->map(function (int $index) use ($currentSlots, $currentValues, $previousSlots, $previousValues): array {
                    $currentSlot = $currentSlots->get($index);
                    $previousSlot = $previousSlots->get($index);

                    return [
                        'date' => $currentSlot['date'] ?? null,
                        'label' => $currentSlot['label'] ?? '—',
                        'current_cents' => (int) $currentValues->get($currentSlot['key'] ?? '', 0),
                        'previous_date' => $previousSlot['date'] ?? null,
                        'previous_label' => $previousSlot['label'] ?? '—',
                        'previous_cents' => (int) $previousValues->get($previousSlot['key'] ?? '', 0),
                    ];
                })->all(),
            ];
        })->all();

        return [
            'granularity' => $this->range->granularity(),
            'series' => $series,
            'is_empty' => collect($series)->flatMap(fn (array $item): array => $item['points'])
                ->every(fn (array $point): bool => $point['current_cents'] === 0 && $point['previous_cents'] === 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function funnel(): array
    {
        $currentEvents = $this->events(
            self::FUNNEL_EVENT_NAMES,
            $this->range->startUtc(),
            $this->range->endExclusiveUtc(),
        );
        $previousEvents = $this->events(
            self::FUNNEL_EVENT_NAMES,
            $this->range->previousStartUtc(),
            $this->range->previousEndExclusiveUtc(),
        );
        $currentPurchases = $this->purchaseTransactions(
            $this->capturedOrders($this->range->startUtc(), $this->range->endExclusiveUtc()),
        );
        $previousPurchases = $this->purchaseTransactions(
            $this->capturedOrders($this->range->previousStartUtc(), $this->range->previousEndExclusiveUtc()),
        );

        $current = $this->funnelPeriod($currentEvents, $currentPurchases);
        $previous = $this->funnelPeriod($previousEvents, $previousPurchases);
        $availableFrom = AccessEvent::query()
            ->whereIn('event_name', self::FUNNEL_EVENT_NAMES)
            ->whereNotNull('occurred_at')
            ->min('occurred_at');
        $availableDate = $availableFrom
            ? CarbonImmutable::parse($availableFrom, 'UTC')->setTimezone($this->range->timezone)->toDateString()
            : null;
        $linkagePartial = $current['purchase_linkage']['unlinked_transactions'] > 0
            || $previous['purchase_linkage']['unlinked_transactions'] > 0;
        $dateCoveragePartial = $availableDate !== null
            && $this->range->startDate() < $availableDate
            && $this->range->endDate() >= $availableDate;
        $purchaseConversion = $current['purchase_linkage']['complete']
            ? $this->rate($current['purchase']['sessions'], $current['checkout']['sessions'])
            : null;

        return [
            'steps' => [
                [
                    'key' => 'visits',
                    'label' => 'Visitas a Store/producto',
                    'current' => $current['visits'],
                    'previous' => $previous['visits'],
                    'conversion' => null,
                    'conversion_reason' => null,
                ],
                [
                    'key' => 'checkout',
                    'label' => 'Checkout iniciado',
                    'current' => $current['checkout'],
                    'previous' => $previous['checkout'],
                    'conversion' => $this->rate($current['checkout']['sessions'], $current['visits']['sessions']),
                    'conversion_reason' => $current['visits']['sessions'] === 0 ? 'zero_denominator' : null,
                ],
                [
                    'key' => 'purchase',
                    'label' => 'Compra completada',
                    'current' => $current['purchase'],
                    'previous' => $previous['purchase'],
                    'conversion' => $purchaseConversion,
                    'conversion_reason' => ! $current['purchase_linkage']['complete']
                        ? 'incomparable_sessions'
                        : ($current['checkout']['sessions'] === 0 ? 'zero_denominator' : null),
                ],
            ],
            'failed' => $current['failed'],
            'purchase_linkage' => [
                'current' => $current['purchase_linkage'],
                'previous' => $previous['purchase_linkage'],
            ],
            'available_from' => $availableDate,
            'coverage_partial' => $dateCoveragePartial || $linkagePartial,
            'coverage_unavailable' => $availableDate === null || $this->range->endDate() < $availableDate,
            'coverage_message' => $linkagePartial
                ? sprintf(
                    'Cobertura parcial: %d transacciones del rango actual y %d del período anterior no tienen una sesión analítica trazable. La conversión de compra se muestra como N/A cuando el rango actual no es comparable.',
                    $current['purchase_linkage']['unlinked_transactions'],
                    $previous['purchase_linkage']['unlinked_transactions'],
                )
                : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function products(string $sort = 'sales'): array
    {
        $captures = $this->capturedOrders($this->range->startUtc(), $this->range->endExclusiveUtc());
        $refunds = $this->refunds($this->range->startUtc(), $this->range->endExclusiveUtc());
        $rows = collect();

        $captures->each(function (Order $order) use ($rows): void {
            $key = $order->product_key.'|'.$order->currency;
            $row = $rows->get($key, $this->emptyProductRow($order));
            $row['net_cents'] += (int) $order->amount_cents;
            $row['units']++;
            $row['transaction_keys'][$this->transactionKey($order)] = true;
            $rows->put($key, $row);
        });

        $refunds->each(function (OrderRefund $refund) use ($rows): void {
            $order = $refund->order;

            if (! $order) {
                return;
            }

            $key = $order->product_key.'|'.$order->currency;
            $row = $rows->get($key, $this->emptyProductRow($order));
            $row['net_cents'] -= (int) $refund->amount_cents;
            $rows->put($key, $row);
        });

        return $rows->map(function (array $row): array {
            $row['orders'] = count($row['transaction_keys']);
            unset($row['transaction_keys']);

            return $row;
        })
            ->sortByDesc($sort === 'units' ? 'units' : 'net_cents')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function content(): array
    {
        $events = $this->events(
            self::CONTENT_EVENT_NAMES,
            $this->range->startUtc(),
            $this->range->endExclusiveUtc(),
        );
        $rows = $events->groupBy(fn (AccessEvent $event): string => implode('|', [
            $event->event_name,
            $event->resource_type,
            $event->resource_key,
        ]))->map(function (Collection $group): array {
            /** @var AccessEvent $event */
            $event = $group->first();
            $metric = in_array($event->event_name, ['music_play_started', 'video_play_started'], true)
                ? 'Reproducciones'
                : ($event->event_name === 'photo_opened' ? 'Vistas' : 'Interacciones');

            return [
                'type' => $event->resource_type ?: $this->contentType($event->event_name),
                'title' => (string) data_get($event->metadata, 'item_label', $event->resource_key ?: 'Sin título'),
                'resource_key' => $event->resource_key,
                'metric' => $metric,
                'interactions' => $group->count(),
                'sessions' => $group->map($this->eventSessionKey(...))->unique()->count(),
            ];
        })->sortByDesc('interactions')->values()->all();

        $availableFrom = AccessEvent::query()
            ->whereIn('event_name', self::CONTENT_EVENT_NAMES)
            ->whereNotNull('occurred_at')
            ->min('occurred_at');

        $availableDate = $availableFrom
            ? CarbonImmutable::parse($availableFrom, 'UTC')->setTimezone($this->range->timezone)->toDateString()
            : null;

        return [
            'rows' => $rows,
            'available_from' => $availableDate,
            'coverage_unavailable' => $availableDate === null || $this->range->endDate() < $availableDate,
            'coverage_partial' => $availableDate !== null
                && $this->range->startDate() < $availableDate
                && $this->range->endDate() >= $availableDate,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function shows(): array
    {
        $rows = collect();
        $events = FanEvent::query()->get()->keyBy('id');

        $events->each(function (FanEvent $event) use ($rows): void {
            $key = $this->showKey($event);
            $rows->put($key, $this->emptyShowRow($key, $event->title));
        });

        Rsvp::query()
            ->where('created_at', '>=', $this->range->startUtc())
            ->where('created_at', '<', $this->range->endExclusiveUtc())
            ->get()
            ->each(function (Rsvp $rsvp) use ($rows): void {
                $key = (string) $rsvp->event_key;
                $row = $rows->get($key, $this->emptyShowRow($key, $rsvp->event_name));
                $row['rsvps'] += max(1, (int) data_get($rsvp->metadata, 'ticket_quantity', 1));
                $rows->put($key, $row);
            });

        Ticket::query()
            ->with(['event:id,title,metadata', 'order:id,status'])
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('created_at', '>=', $this->range->startUtc())
                        ->where('created_at', '<', $this->range->endExclusiveUtc());
                })->orWhere(function (Builder $query): void {
                    $query->where('purchased_at', '>=', $this->range->startUtc())
                        ->where('purchased_at', '<', $this->range->endExclusiveUtc());
                })->orWhere(function (Builder $query): void {
                    $query->where('checked_in_at', '>=', $this->range->startUtc())
                        ->where('checked_in_at', '<', $this->range->endExclusiveUtc());
                });
            })
            ->get()
            ->each(function (Ticket $ticket) use ($rows): void {
                if (! $ticket->event) {
                    return;
                }

                $key = $this->showKey($ticket->event);
                $row = $rows->get($key, $this->emptyShowRow($key, $ticket->event->title));

                if (
                    $ticket->order_id === null
                    && $ticket->rsvp_status === 'confirmed'
                    && in_array($ticket->status, ['reserved', 'confirmed', 'checked_in'], true)
                    && $this->within($ticket->created_at, $this->range->startUtc(), $this->range->endExclusiveUtc())
                ) {
                    $row['rsvps']++;
                }

                $isPaidTicket = $ticket->order_id !== null
                    && in_array($ticket->order?->status, self::COMPLETED_ORDER_STATUSES, true);
                $wasPurchasedInRange = $isPaidTicket
                    && $this->within($ticket->purchased_at, $this->range->startUtc(), $this->range->endExclusiveUtc());

                if (
                    $ticket->order_id !== null
                    && $wasPurchasedInRange
                ) {
                    $row['tickets']++;
                }

                if ($this->within($ticket->checked_in_at, $this->range->startUtc(), $this->range->endExclusiveUtc())) {
                    $row['checkins']++;

                    if ($wasPurchasedInRange) {
                        $row['paid_ticket_checkins']++;
                    }
                }

                $rows->put($key, $row);
            });

        return $rows->map(function (array $row): array {
            $row['rsvp_to_ticket'] = $this->rate($row['tickets'], $row['rsvps']);
            $row['ticket_to_checkin'] = $this->rate($row['paid_ticket_checkins'], $row['tickets']);
            unset($row['paid_ticket_checkins']);

            return $row;
        })
            ->filter(fn (array $row): bool => $row['rsvps'] > 0 || $row['tickets'] > 0 || $row['checkins'] > 0)
            ->sortByDesc(fn (array $row): int => $row['rsvps'] + $row['tickets'] + $row['checkins'])
            ->values()
            ->all();
    }

    /**
     * @return array{status: string, data: mixed, message: string|null}
     */
    private function resolve(callable $resolver): array
    {
        try {
            return ['status' => 'ready', 'data' => $resolver(), 'message' => null];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'status' => 'error',
                'data' => null,
                'message' => 'No pudimos consultar este módulo. Los demás reportes siguen disponibles.',
            ];
        }
    }

    /**
     * @return Collection<int, Order>
     */
    private function capturedOrders(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $key = $start->getTimestamp().':'.$end->getTimestamp();

        return $this->capturedOrderCache[$key] ??= Order::query()
            ->whereIn('status', self::COMPLETED_ORDER_STATUSES)
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->get();
    }

    /**
     * @return Collection<int, OrderRefund>
     */
    private function refunds(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $key = $start->getTimestamp().':'.$end->getTimestamp();

        return $this->refundCache[$key] ??= OrderRefund::query()
            ->with('order')
            ->where('refunded_at', '>=', $start)
            ->where('refunded_at', '<', $end)
            ->get();
    }

    /**
     * @param  Collection<int, Order>  $captures
     * @param  Collection<int, OrderRefund>  $refunds
     * @return Collection<string, int>
     */
    private function netSalesByCurrency(Collection $captures, Collection $refunds): Collection
    {
        $totals = collect();

        $captures->each(fn (Order $order) => $totals->put(
            $order->currency,
            (int) $totals->get($order->currency, 0) + (int) $order->amount_cents,
        ));
        $refunds->each(fn (OrderRefund $refund) => $totals->put(
            $refund->currency,
            (int) $totals->get($refund->currency, 0) - (int) $refund->amount_cents,
        ));

        return $totals;
    }

    /**
     * @param  Collection<int, Order>  $captures
     * @param  Collection<int, OrderRefund>  $refunds
     * @return Collection<string, Collection<string, int>>
     */
    private function netSalesByBucket(
        Collection $captures,
        Collection $refunds,
        CarbonImmutable $periodStart,
    ): Collection {
        $totals = collect();

        $captures->each(function (Order $order) use ($periodStart, $totals): void {
            $currency = $order->currency;
            $bucket = $this->bucketKey($order->completed_at ?? $order->created_at, $periodStart);
            $values = $totals->get($currency, collect());
            $values->put($bucket, (int) $values->get($bucket, 0) + (int) $order->amount_cents);
            $totals->put($currency, $values);
        });
        $refunds->each(function (OrderRefund $refund) use ($periodStart, $totals): void {
            $currency = $refund->currency;
            $bucket = $this->bucketKey($refund->refunded_at, $periodStart);
            $values = $totals->get($currency, collect());
            $values->put($bucket, (int) $values->get($bucket, 0) - (int) $refund->amount_cents);
            $totals->put($currency, $values);
        });

        return $totals;
    }

    /**
     * @return Collection<int, array{key: string, date: string, label: string}>
     */
    private function slots(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $slots = collect();
        $index = 0;

        while (true) {
            $cursor = $this->range->granularity() === 'day'
                ? $start->addDays($index)
                : $start->addMonthsNoOverflow($index);

            if ($cursor->gte($end)) {
                break;
            }

            $slots->push([
                'key' => $cursor->format('Y-m-d'),
                'date' => $cursor->toDateString(),
                'label' => $this->range->granularity() === 'day' ? $cursor->format('M j') : $cursor->format('M Y'),
            ]);
            $index++;
        }

        return $slots;
    }

    private function bucketKey(?CarbonInterface $timestamp, CarbonImmutable $periodStart): string
    {
        if (! $timestamp) {
            return '';
        }

        $local = CarbonImmutable::instance($timestamp)->setTimezone($this->range->timezone);

        if ($this->range->granularity() === 'day') {
            return $local->format('Y-m-d');
        }

        $index = 0;
        $bucketStart = $periodStart;

        while ($periodStart->addMonthsNoOverflow($index + 1)->lte($local)) {
            $index++;
            $bucketStart = $periodStart->addMonthsNoOverflow($index);
        }

        return $bucketStart->format('Y-m-d');
    }

    /**
     * @param  array<int, string>  $names
     * @return Collection<int, AccessEvent>
     */
    private function events(array $names, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $sortedNames = $names;
        sort($sortedNames);
        $key = implode(',', $sortedNames).':'.$start->getTimestamp().':'.$end->getTimestamp();

        return $this->eventCache[$key] ??= AccessEvent::query()
            ->whereIn('event_name', $names)
            ->where(function (Builder $query) use ($end, $start): void {
                $query->where(function (Builder $query) use ($end, $start): void {
                    $query->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end);
                })->orWhere(function (Builder $query) use ($end, $start): void {
                    $query->whereNull('occurred_at')
                        ->where('created_at', '>=', $start)
                        ->where('created_at', '<', $end);
                });
            })
            ->get();
    }

    /**
     * @return array{status: string, legacy_orders: int, message: string|null}
     */
    private function commerceCoverage(): array
    {
        $legacyOrders = Order::query()
            ->whereIn('status', self::COMPLETED_ORDER_STATUSES)
            ->whereNull('completed_at')
            ->where('created_at', '>=', $this->range->previousStartUtc())
            ->where('created_at', '<', $this->range->endExclusiveUtc())
            ->count();

        return [
            'status' => $legacyOrders > 0 ? 'partial' : 'complete',
            'legacy_orders' => $legacyOrders,
            'message' => $legacyOrders > 0
                ? 'Cobertura transaccional parcial: se excluyeron órdenes históricas sin fecha de captura verificable.'
                : null,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, array{key: string, session_id: string|null}>
     */
    private function purchaseTransactions(Collection $orders): Collection
    {
        return $orders
            ->groupBy($this->transactionKey(...))
            ->map(function (Collection $transactionOrders, string $transactionKey): array {
                $sessionIds = $transactionOrders
                    ->map(fn (Order $order): mixed => data_get($order->metadata, 'checkout.analytics_session_id'))
                    ->filter(fn (mixed $sessionId): bool => is_string($sessionId)
                        && preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $sessionId) === 1)
                    ->unique()
                    ->values();

                return [
                    'key' => $transactionKey,
                    'session_id' => $sessionIds->count() === 1 ? $sessionIds->first() : null,
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, AccessEvent>  $events
     * @param  Collection<int, array{key: string, session_id: string|null}>  $purchases
     * @return array<string, mixed>
     */
    private function funnelPeriod(Collection $events, Collection $purchases): array
    {
        $visits = $events->filter(fn (AccessEvent $event): bool => (
            ($event->event_name === 'page_view' && $event->resource_key === 'store')
            || $event->event_name === 'store_product_opened'
        ));
        $checkout = $events->filter(fn (AccessEvent $event): bool => (
            $event->event_name === 'store_checkout_started'
            && $event->result !== 'empty'
        ));
        $failed = $events->where('event_name', 'store_payment_failed');
        $checkoutSessions = $checkout->map($this->eventSessionKey(...))->unique()->values();
        $linkedPurchases = $purchases
            ->filter(fn (array $purchase): bool => $purchase['session_id'] !== null
                && $checkoutSessions->containsStrict($purchase['session_id']))
            ->values();
        $unlinkedTransactions = $purchases->count() - $linkedPurchases->count();

        return [
            'visits' => $this->eventCounts($visits),
            'checkout' => $this->eventCounts($checkout),
            'purchase' => [
                'sessions' => $linkedPurchases->pluck('session_id')->unique()->count(),
                'events' => $purchases->count(),
            ],
            'purchase_linkage' => [
                'complete' => $unlinkedTransactions === 0,
                'linked_transactions' => $linkedPurchases->count(),
                'unlinked_transactions' => $unlinkedTransactions,
            ],
            'failed' => $this->eventCounts($failed),
        ];
    }

    /**
     * @param  Collection<int, AccessEvent>  $events
     * @return array{sessions: int, events: int}
     */
    private function eventCounts(Collection $events): array
    {
        return [
            'sessions' => $events->map($this->eventSessionKey(...))->unique()->count(),
            'events' => $events->count(),
        ];
    }

    private function eventSessionKey(AccessEvent $event): string
    {
        return $event->session_id ?: 'legacy-event:'.$event->id;
    }

    /**
     * @return Collection<int, AccessEvent>
     */
    private function humanPageViews(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $pageViews = $this->comparisonPageViews ??= $this->events(
            ['page_view'],
            $this->range->previousStartUtc(),
            $this->range->endExclusiveUtc(),
        )->filter(fn (AccessEvent $event): bool => $event->device_category !== 'bot')->values();

        return $pageViews
            ->filter(function (AccessEvent $event) use ($end, $start): bool {
                $timestamp = $event->occurred_at ?? $event->created_at;

                return $timestamp !== null && $timestamp->gte($start) && $timestamp->lt($end);
            })
            ->values();
    }

    /**
     * @return array{visitor_available_from: string|null, traffic_available_from: string|null}
     */
    private function analyticsCoverage(): array
    {
        if ($this->analyticsCoverage !== null) {
            return $this->analyticsCoverage;
        }

        $coverage = AccessEvent::query()
            ->where('event_name', 'page_view')
            ->selectRaw('MIN(CASE WHEN visitor_id IS NOT NULL THEN occurred_at END) AS visitor_available_from')
            ->selectRaw('MIN(CASE WHEN traffic_source IS NOT NULL THEN occurred_at END) AS traffic_available_from')
            ->first();

        return $this->analyticsCoverage = [
            'visitor_available_from' => $coverage?->getRawOriginal('visitor_available_from'),
            'traffic_available_from' => $coverage?->getRawOriginal('traffic_available_from'),
        ];
    }

    private function analyticsPeriodCoverage(
        ?string $availableDate,
        string $startDate,
        string $endDate,
        int $measuredPageViews,
        int $observedPageViews,
    ): string {
        if ($availableDate === null || $endDate < $availableDate) {
            return 'unavailable';
        }

        if ($startDate < $availableDate || $measuredPageViews < $observedPageViews) {
            return 'partial';
        }

        return 'complete';
    }

    /**
     * @param  Collection<int, AccessEvent>  $events
     * @return array{visitors: int, sessions: int, page_views: int, observed_page_views: int, identified_page_views: int, new_visitors: int, returning_visitors: int}
     */
    private function audiencePeriod(Collection $events, CarbonImmutable $start): array
    {
        $identifiedEvents = $events->filter(fn (AccessEvent $event): bool => $event->visitor_id !== null);
        $visitorIds = $identifiedEvents->pluck('visitor_id')->unique()->values();
        $returningVisitors = $visitorIds->isEmpty() ? 0 : AccessEvent::query()
            ->where('event_name', 'page_view')
            ->whereIn('visitor_id', $visitorIds)
            ->where(function (Builder $query) use ($start): void {
                $query->where('occurred_at', '<', $start)
                    ->orWhere(function (Builder $query) use ($start): void {
                        $query->whereNull('occurred_at')->where('created_at', '<', $start);
                    });
            })
            ->distinct()
            ->count('visitor_id');

        return [
            'visitors' => $visitorIds->count(),
            'sessions' => $identifiedEvents->pluck('session_id')->filter()->unique()->count(),
            'page_views' => $identifiedEvents->count(),
            'observed_page_views' => $events->count(),
            'identified_page_views' => $identifiedEvents->count(),
            'new_visitors' => $visitorIds->count() - $returningVisitors,
            'returning_visitors' => $returningVisitors,
        ];
    }

    /**
     * @param  Collection<int, AccessEvent>  $current
     * @param  Collection<int, AccessEvent>  $previous
     * @param  array<int, string>  $dimensions
     * @return array<int, array<string, mixed>>
     */
    private function dimensionRows(
        Collection $current,
        Collection $previous,
        array $dimensions,
        bool $comparisonAvailable = true,
    ): array {
        $group = function (Collection $events) use ($dimensions): Collection {
            return $events->groupBy(function (AccessEvent $event) use ($dimensions): string {
                return collect($dimensions)->map(
                    fn (string $dimension): string => (string) ($event->{$dimension} ?: 'unknown'),
                )->implode('|');
            });
        };
        $currentGroups = $group($current);
        $previousGroups = $group($previous);

        $dimensionKeys = $comparisonAvailable
            ? [...$currentGroups->keys(), ...$previousGroups->keys()]
            : $currentGroups->keys()->all();

        return collect($dimensionKeys)
            ->unique()
            ->map(function (string $key) use ($comparisonAvailable, $currentGroups, $dimensions, $previousGroups): array {
                $currentEvents = $currentGroups->get($key, collect());
                $previousEvents = $previousGroups->get($key, collect());
                $values = explode('|', $key);
                $row = [];

                foreach ($dimensions as $index => $dimension) {
                    $row[$dimension] = ($values[$index] ?? 'unknown') === 'unknown'
                        ? null
                        : $values[$index];
                }

                return [
                    ...$row,
                    'visitors' => $currentEvents->pluck('visitor_id')->filter()->unique()->count(),
                    'sessions' => $currentEvents->pluck('session_id')->filter()->unique()->count(),
                    'page_views' => $currentEvents->count(),
                    'previous_sessions' => $comparisonAvailable
                        ? $previousEvents->pluck('session_id')->filter()->unique()->count()
                        : null,
                ];
            })
            ->sortByDesc('sessions')
            ->values()
            ->all();
    }

    private function transactionKey(Order $order): string
    {
        return (string) ($order->provider_capture_id ?: $order->provider_order_id ?: 'order:'.$order->id);
    }

    private function newUsers(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return User::query()
            ->whereNotIn('role', [
                User::ROLE_ADMIN,
                User::ROLE_ARTIST_ADMIN,
                User::ROLE_EDITOR,
                User::ROLE_MODERATOR,
            ])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();
    }

    /**
     * @return array{current: int, previous: int|null, absolute: int|null, percent: float|null}
     */
    private function comparison(int $current, int $previous, bool $comparisonAvailable = true): array
    {
        if (! $comparisonAvailable) {
            return [
                'current' => $current,
                'previous' => null,
                'absolute' => null,
                'percent' => null,
            ];
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'absolute' => $current - $previous,
            'percent' => $previous === 0 ? null : round((($current - $previous) / abs($previous)) * 100, 1),
        ];
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        return $denominator === 0 ? null : round(($numerator / $denominator) * 100, 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyProductRow(Order $order): array
    {
        return [
            'product_key' => $order->product_key,
            'title' => (string) data_get($order->metadata, 'product.title', str($order->product_key)->headline()->toString()),
            'type' => (string) data_get($order->metadata, 'product.type', data_get($order->metadata, 'product.unlock_type', 'product')),
            'currency' => $order->currency,
            'net_cents' => 0,
            'units' => 0,
            'transaction_keys' => [],
        ];
    }

    private function contentType(string $eventName): string
    {
        return match ($eventName) {
            'music_play_started' => 'music',
            'video_play_started' => 'video',
            'photo_opened' => 'photo',
            default => 'community',
        };
    }

    private function showKey(FanEvent $event): string
    {
        return (string) (data_get($event->metadata, 'store_event_key') ?: 'event-'.$event->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyShowRow(string $key, string $title): array
    {
        return [
            'event_key' => $key,
            'title' => $title,
            'rsvps' => 0,
            'tickets' => 0,
            'checkins' => 0,
            'paid_ticket_checkins' => 0,
            'rsvp_to_ticket' => null,
            'ticket_to_checkin' => null,
        ];
    }

    private function within(?CarbonInterface $timestamp, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $timestamp !== null && $timestamp->gte($start) && $timestamp->lt($end);
    }
}
