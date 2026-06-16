<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.dashboard', [
            'canPublish' => $request->user()->canPublishContent(),
            'queueItems' => [
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
            ],
        ]);
    }
}
