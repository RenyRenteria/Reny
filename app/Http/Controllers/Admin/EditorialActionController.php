<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Enums\VisibilityAudience;
use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Services\EditorialWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EditorialActionController extends Controller
{
    public function saveDraft(Request $request, EditorialWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        $payload = $this->validatedPayload($request);

        $content = $workflow->createDraft($request->user(), $payload);

        $message = sprintf(
            'Draft "%s" saved for approval.',
            $content->title
        );

        if ($request->expectsJson()) {
            return response()->json($this->responsePayload($content, $message));
        }

        return back()->with('status', $message);
    }

    public function publish(Request $request, EditorialWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        $payload = $this->validatedPayload($request, true);

        $content = isset($payload['content_id'])
            ? $workflow->publish($request->user(), EditorialContent::query()->findOrFail($payload['content_id']), $payload)
            : $workflow->publishNew($request->user(), $payload);

        $message = sprintf(
            'Content "%s" approved and published.',
            $content->title
        );

        if ($request->expectsJson()) {
            return response()->json($this->responsePayload($content, $message));
        }

        return back()->with('status', $message);
    }

    private function validatedPayload(Request $request, bool $allowExistingContent = false): array
    {
        return $request->validate([
            'content_id' => [
                $allowExistingContent ? 'nullable' : 'prohibited',
                'integer',
                Rule::exists('editorial_contents', 'id'),
            ],
            'type' => ['nullable', Rule::in(ContentType::values())],
            'title' => ['nullable', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'visibility' => ['nullable', Rule::in(VisibilityAudience::values())],
            'purchase_key' => ['nullable', 'string', 'max:120'],
            'scheduled_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'release_windows' => ['nullable', 'array'],
            'release_windows.*.audience' => ['required_with:release_windows', Rule::in(VisibilityAudience::values())],
            'release_windows.*.starts_at' => ['nullable', 'date'],
            'release_windows.*.ends_at' => ['nullable', 'date'],
            'release_windows.*.country_codes' => ['nullable', 'array'],
            'release_windows.*.country_codes.*' => ['string', 'size:2'],
        ]);
    }

    private function responsePayload(EditorialContent $content, string $message): array
    {
        return [
            'id' => $content->id,
            'message' => $message,
            'type' => $content->type->value,
            'status' => $content->status->value,
            'visibility' => $content->visibility->value,
            'needs_approval' => $content->needs_approval,
        ];
    }
}
