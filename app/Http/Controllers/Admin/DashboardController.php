<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Support\AdminCmsSections;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $countsByType = EditorialContent::query()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $sectionStats = collect(AdminCmsSections::tabs())
            ->map(fn (array $tab, string $section): array => [
                'key' => $section,
                'label' => $tab['label'],
                'caption' => $tab['caption'],
                'accent' => $tab['accent'],
                'total' => collect($tab['types'])->sum(fn (string $type): int => (int) ($countsByType[$type] ?? 0)),
            ])
            ->values();

        $queueItems = EditorialContent::query()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (EditorialContent $content): array => [
                'title' => $content->title,
                'type' => str_replace('_', ' ', $content->type->value),
                'status' => $content->status->value,
                'needsApproval' => $content->needs_approval,
                'section' => AdminCmsSections::sectionForType($content->type),
            ]);

        return view('admin.dashboard', [
            'canPublish' => $request->user()->canPublishContent(),
            'sectionStats' => $sectionStats,
            'queueItems' => $queueItems->isNotEmpty() ? $queueItems : collect([
                [
                    'title' => 'Project 3 CMS readiness',
                    'type' => 'Admin slice',
                    'status' => 'draft',
                    'needsApproval' => true,
                    'section' => 'community',
                ],
                [
                    'title' => 'Royal content release window',
                    'type' => 'Access rules',
                    'status' => 'scheduled',
                    'needsApproval' => false,
                    'section' => 'music',
                ],
            ]),
        ]);
    }
}
