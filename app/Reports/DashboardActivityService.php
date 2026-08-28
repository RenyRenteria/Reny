<?php

namespace App\Reports;

use App\Models\AccessEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class DashboardActivityService
{
    public function __construct(private readonly ReportRange $range) {}

    /**
     * @return array{
     *     homepageSessions: array{current: int|null, previous: int|null, absolute: int|null, percent: float|null, has_error: bool},
     *     paywallViews: array{current: int|null, previous: int|null, absolute: int|null, percent: float|null, has_error: bool},
     *     definitions: array{homepageSessions: string, paywallViews: string}
     * }
     */
    public function metrics(): array
    {
        return [
            'homepageSessions' => $this->resolveComparison(
                fn (CarbonImmutable $start, CarbonImmutable $end): int => $this->homepageSessions($start, $end),
            ),
            'paywallViews' => $this->resolveComparison(
                fn (CarbonImmutable $start, CarbonImmutable $end): int => $this->paywallViews($start, $end),
            ),
            'definitions' => [
                'homepageSessions' => 'Sesiones anónimas distintas con identificador disponible que cargaron el homepage en el rango. Varias visitas o recargas dentro de la misma sesión cuentan una sola vez.',
                'paywallViews' => 'Apariciones de contenido bloqueado registradas en el rango, incluidas las que ocurren al navegar dentro del sitio sin recargar la página.',
            ],
        ];
    }

    /**
     * @return array{current: int|null, previous: int|null, absolute: int|null, percent: float|null, has_error: bool}
     */
    private function resolveComparison(callable $resolver): array
    {
        try {
            $current = $resolver($this->range->startUtc(), $this->range->endExclusiveUtc());
            $previous = $resolver($this->range->previousStartUtc(), $this->range->previousEndExclusiveUtc());

            return [
                'current' => $current,
                'previous' => $previous,
                'absolute' => $current - $previous,
                'percent' => $previous === 0 ? null : round((($current - $previous) / abs($previous)) * 100, 1),
                'has_error' => false,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'current' => null,
                'previous' => null,
                'absolute' => null,
                'percent' => null,
                'has_error' => true,
            ];
        }
    }

    private function homepageSessions(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return $this->events('page_view', $start, $end)
            ->where('resource_type', 'page')
            ->where('resource_key', 'home')
            ->where(function (Builder $query): void {
                $query->whereNull('device_category')->orWhere('device_category', '!=', 'bot');
            })
            ->whereNotNull('session_id')
            ->distinct()
            ->count('session_id');
    }

    private function paywallViews(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return $this->events('permission_denied', $start, $end)
            ->where('resource_type', 'access_gate')
            ->count();
    }

    private function events(string $eventName, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return AccessEvent::query()
            ->where('event_name', $eventName)
            ->where(function (Builder $query) use ($end, $start): void {
                $query->where(function (Builder $query) use ($end, $start): void {
                    $query->where('occurred_at', '>=', $start)
                        ->where('occurred_at', '<', $end);
                })->orWhere(function (Builder $query) use ($end, $start): void {
                    $query->whereNull('occurred_at')
                        ->where('created_at', '>=', $start)
                        ->where('created_at', '<', $end);
                });
            });
    }
}
