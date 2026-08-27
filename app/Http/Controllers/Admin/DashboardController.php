<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\DashboardActivityService;
use App\Reports\DashboardReportService;
use App\Reports\ReportRange;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $range = ReportRange::fromRequest($request);
        $productSort = (string) $request->query('product_sort', 'sales');
        $reports = (new DashboardReportService($range))->modules($productSort);
        $activityStats = (new DashboardActivityService($range))->metrics();

        return view('admin.stats', [
            'range' => $range,
            'reports' => $reports,
            'activityStats' => $activityStats,
            'productSort' => $productSort,
        ]);
    }
}
