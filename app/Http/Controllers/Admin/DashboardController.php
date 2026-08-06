<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportDashboardService;
use App\Support\Reports\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportDashboardService $reports): View
    {
        $validated = $request->validate([
            'product_sort' => ['nullable', 'string', Rule::in(['sales', 'units'])],
        ]);
        $period = ReportPeriod::fromRequest($request);
        $productSort = (string) ($validated['product_sort'] ?? 'sales');

        return view('admin.stats', $reports->dashboard($period, $productSort));
    }
}
