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
    private const REPORTS = ['summary', 'sales', 'funnel', 'products', 'content', 'shows'];

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
    private function funnelRows(array $data): array
    {
        $rows = collect($data['steps'])->map(fn (array $step): array => [
            $step['key'],
            $step['current']['sessions'],
            $step['current']['events'],
            $step['previous']['sessions'],
            $step['previous']['events'],
            $step['conversion'],
            $data['available_from'],
        ])->all();
        $rows[] = ['failed_payments', $data['failed']['sessions'], $data['failed']['events'], null, null, null, $data['available_from']];

        return [[
            'step',
            'sessions',
            'events',
            'previous_sessions',
            'previous_events',
            'conversion_percent',
            'available_from',
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
