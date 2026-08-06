<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportDashboardService;
use App\Support\Reports\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $report, ReportDashboardService $reports): StreamedResponse
    {
        abort_unless(in_array($report, ['summary', 'sales', 'funnel', 'products', 'content', 'shows'], true), 404);

        $validated = $request->validate([
            'product_sort' => ['nullable', 'string', Rule::in(['sales', 'units'])],
        ]);
        $period = ReportPeriod::fromRequest($request);
        $export = $reports->export($report, $period, (string) ($validated['product_sort'] ?? 'sales'));
        $filename = sprintf(
            '%s-%s-%s.csv',
            $report,
            $period->start->toDateString(),
            $period->end->toDateString(),
        );

        return response()->streamDownload(function () use ($export): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fputcsv($stream, $export['headers']);

            foreach ($export['rows'] as $row) {
                fputcsv($stream, $row);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
