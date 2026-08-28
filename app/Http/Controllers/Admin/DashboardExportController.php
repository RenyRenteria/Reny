<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\DashboardReportService;
use App\Reports\ReportRange;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardExportController extends Controller
{
    private const REPORTS = ['summary', 'audience', 'acquisition', 'sales', 'funnel', 'products', 'content', 'shows'];

    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'report' => ['required', 'string', Rule::in(self::REPORTS)],
        ]);
        $range = ReportRange::fromRequest($request);
        $report = (string) $validated['report'];
        $service = new DashboardReportService($range);
        [$headers, $rows] = $this->rows($report, $service, (string) $request->query('product_sort', 'sales'));
        $filename = "{$report}_{$range->filenameSuffix()}.csv";

        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map(fn (mixed $cell): mixed => $this->csvCell($cell), $headers));

            foreach ($rows as $row) {
                fputcsv($output, array_map(fn (mixed $cell): mixed => $this->csvCell($cell), $row));
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|float|null>>}
     */
    private function rows(string $report, DashboardReportService $service, string $productSort): array
    {
        return match ($report) {
            'summary' => $this->summaryRows($service->kpis()),
            'audience' => $this->audienceRows($service->audience()),
            'acquisition' => $this->acquisitionRows($service->acquisition()),
            'sales' => $this->salesRows($service->salesSeries()),
            'funnel' => $this->funnelRows($service->funnel()),
            'products' => $this->productRows($service->products($productSort)),
            'content' => $this->contentRows($service->content()),
            'shows' => $this->showRows($service->shows()),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function summaryRows(array $data): array
    {
        $headers = ['metric', 'currency', 'current', 'previous', 'absolute_change', 'percent_change'];
        $rows = collect($data['sales'])->map(fn (array $sales): array => [
            'net_sales',
            $sales['currency'],
            $this->majorUnits($sales['current']),
            $this->majorUnits($sales['previous']),
            $this->majorUnits($sales['absolute']),
            $sales['percent'],
        ])->all();
        $rows[] = ['completed_orders', null, $data['orders']['current'], $data['orders']['previous'], $data['orders']['absolute'], $data['orders']['percent']];
        $rows[] = ['active_royals_current_snapshot', null, $data['royals']['current'], null, null, null];
        $rows[] = ['new_users', null, $data['users']['current'], $data['users']['previous'], $data['users']['absolute'], $data['users']['percent']];

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function salesRows(array $data): array
    {
        $rows = collect($data['series'])->flatMap(fn (array $series): array => collect($series['points'])
            ->map(fn (array $point): array => [
                $point['date'],
                $this->majorUnits($point['current_cents']),
                $point['previous_date'],
                $this->majorUnits($point['previous_cents']),
                $series['currency'],
            ])->all())
            ->values()
            ->all();

        return [['date', 'net_sales', 'previous_period_date', 'previous_period_net_sales', 'currency'], $rows];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function audienceRows(array $data): array
    {
        $headers = [
            'metric',
            'current',
            'previous',
            'absolute_change',
            'percent_change',
            'available_from',
            'current_coverage_status',
            'previous_coverage_status',
            'comparison_available',
        ];
        $rows = collect([
            'unique_visitors' => $data['visitors'],
            'sessions' => $data['sessions'],
            'page_views' => $data['page_views'],
            'new_visitors' => $data['new_visitors'],
            'returning_visitors' => $data['returning_visitors'],
        ])->map(fn (array $metric, string $key): array => [
            $key,
            $metric['current'],
            $metric['previous'],
            $metric['absolute'],
            $metric['percent'],
            $data['available_from'],
            $data['current_coverage_status'],
            $data['previous_coverage_status'],
            $data['comparison_available'] ? 'true' : 'false',
        ])->values()->all();

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function acquisitionRows(array $data): array
    {
        $headers = [
            'dimension',
            'source',
            'medium',
            'campaign',
            'device',
            'country',
            'visitors',
            'sessions',
            'page_views',
            'previous_sessions',
            'available_from',
            'current_coverage_status',
            'previous_coverage_status',
            'comparison_available',
        ];
        $mapRows = fn (array $rows, string $dimension): array => collect($rows)
            ->map(fn (array $row): array => [
                $dimension,
                $row['traffic_source'] ?? null,
                $row['traffic_medium'] ?? null,
                $row['traffic_campaign'] ?? null,
                $row['device_category'] ?? null,
                $row['country_code'] ?? null,
                $row['visitors'],
                $row['sessions'],
                $row['page_views'],
                $row['previous_sessions'],
                $data['available_from'],
                $data['current_coverage_status'],
                $data['previous_coverage_status'],
                $data['comparison_available'] ? 'true' : 'false',
            ])->all();
        $rows = [
            ...$mapRows($data['channels'], 'channel'),
            ...$mapRows($data['devices'], 'device'),
            ...$mapRows($data['countries'], 'country'),
        ];

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function funnelRows(array $data): array
    {
        $rows = collect($data['steps'])->map(fn (array $step): array => [
            $step['key'],
            $step['current']['sessions'],
            $step['current']['events'],
            $step['previous']['sessions'],
            $step['previous']['events'],
            $step['conversion'],
            $step['conversion_reason'],
            $step['key'] === 'purchase' ? $data['purchase_linkage']['current']['unlinked_transactions'] : null,
            $data['available_from'],
            $data['coverage_message'],
        ])->all();
        $rows[] = ['failed_payments', $data['failed']['sessions'], $data['failed']['events'], null, null, null, null, null, $data['available_from'], $data['coverage_message']];

        return [[
            'step',
            'sessions',
            'events',
            'previous_sessions',
            'previous_events',
            'conversion_percent',
            'conversion_reason',
            'unlinked_purchase_transactions',
            'available_from',
            'coverage_message',
        ], $rows];
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    private function productRows(array $data): array
    {
        return [[
            'product_key',
            'title',
            'type',
            'net_sales',
            'currency',
            'units',
            'orders',
        ], collect($data)->map(fn (array $row): array => [
            $row['product_key'],
            $row['title'],
            $row['type'],
            $this->majorUnits($row['net_cents']),
            $row['currency'],
            $row['units'],
            $row['orders'],
        ])->all()];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function contentRows(array $data): array
    {
        return [[
            'type',
            'resource_key',
            'title',
            'metric',
            'interactions',
            'unique_sessions',
            'available_from',
        ], collect($data['rows'])->map(fn (array $row): array => [
            $row['type'],
            $row['resource_key'],
            $row['title'],
            $row['metric'],
            $row['interactions'],
            $row['sessions'],
            $data['available_from'],
        ])->all()];
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    private function showRows(array $data): array
    {
        return [[
            'event_key',
            'title',
            'confirmed_rsvps',
            'tickets_sold',
            'checkins',
            'rsvp_to_ticket_percent',
            'ticket_to_checkin_percent',
        ], collect($data)->map(fn (array $row): array => [
            $row['event_key'],
            $row['title'],
            $row['rsvps'],
            $row['tickets'],
            $row['checkins'],
            $row['rsvp_to_ticket'],
            $row['ticket_to_checkin'],
        ])->all()];
    }

    private function majorUnits(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function csvCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $first = $value[0];
        $startsFormula = in_array($first, ['=', '+', '-', '@', "\t", "\r", "\n"], true)
            || preg_match('/^[\x00-\x20]+[=+\-@]/', $value) === 1;

        return $startsFormula ? "'".$value : $value;
    }
}
