<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EditorialActionController extends Controller
{
    public function saveDraft(Request $request): JsonResponse|RedirectResponse
    {
        $payload = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
        ]);

        $message = sprintf(
            'Draft "%s" saved for approval.',
            $payload['title'] ?? 'Untitled content'
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => 'draft',
                'needs_approval' => true,
            ]);
        }

        return back()->with('status', $message);
    }

    public function publish(Request $request): JsonResponse|RedirectResponse
    {
        $payload = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
        ]);

        $message = sprintf(
            'Content "%s" approved and published.',
            $payload['title'] ?? 'Untitled content'
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => 'published',
                'needs_approval' => false,
            ]);
        }

        return back()->with('status', $message);
    }
}
