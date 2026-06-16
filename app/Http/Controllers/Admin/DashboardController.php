<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $queueItems = EditorialContent::query()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (EditorialContent $content): array => [
                'title' => $content->title,
                'type' => str_replace('_', ' ', $content->type->value),
                'status' => $content->status->value,
                'needsApproval' => $content->needs_approval,
            ]);

        return view('admin.dashboard', [
            'canPublish' => $request->user()->canPublishContent(),
            'queueItems' => $queueItems->isNotEmpty() ? $queueItems : collect([
                [
                    'title' => 'Project 3 CMS readiness',
                    'type' => 'Admin slice',
                    'status' => 'draft',
                    'needsApproval' => true,
                ],
                [
                    'title' => 'Royal content release window',
                    'type' => 'Access rules',
                    'status' => 'scheduled',
                    'needsApproval' => false,
                ],
            ]),
        ]);
    }
}
