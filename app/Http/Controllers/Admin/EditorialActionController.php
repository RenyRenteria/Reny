<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Enums\VisibilityAudience;
use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Services\EditorialWorkflowService;
use App\Support\EditorialContentForms;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EditorialActionController extends Controller
{
    private const SCHEDULING_TIMEZONE = 'America/Panama';

    private const STORAGE_TIMEZONE = 'UTC';

    public function __construct(private readonly EditorialContentForms $forms) {}

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

    public function updateDraft(
        Request $request,
        EditorialContent $content,
        EditorialWorkflowService $workflow
    ): JsonResponse|RedirectResponse {
        $payload = $this->validatedPayload($request, true);

        $content = $workflow->updateDraft($request->user(), $content, $payload);

        $message = sprintf(
            'Draft "%s" updated.',
            $content->title
        );

        if ($request->expectsJson()) {
            return response()->json($this->responsePayload($content, $message));
        }

        return redirect()
            ->route('admin.editorial.edit', $content)
            ->with('status', $message);
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

    public function schedule(Request $request, EditorialWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        $payload = $this->validatedPayload($request, true, scheduledAtRequired: true);
        $scheduledAt = $payload['scheduled_at'];
        $releaseWindows = $payload['release_windows'] ?? [];

        $content = isset($payload['content_id'])
            ? $workflow->schedule(
                $request->user(),
                EditorialContent::query()->findOrFail($payload['content_id']),
                $scheduledAt,
                $releaseWindows,
                $payload
            )
            : $workflow->scheduleNew($request->user(), $payload, $scheduledAt);

        $message = sprintf(
            'Content "%s" scheduled for %s Panama time.',
            $content->title,
            $content->scheduled_at?->timezone(self::SCHEDULING_TIMEZONE)->format('M j, Y g:i A')
        );

        if ($request->expectsJson()) {
            return response()->json($this->responsePayload($content, $message));
        }

        return redirect()
            ->route('admin.editorial.edit', $content)
            ->with('status', $message);
    }

    private function validatedPayload(
        Request $request,
        bool $allowExistingContent = false,
        bool $scheduledAtRequired = false
    ): array {
        $rules = [
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
            'scheduled_at' => [$scheduledAtRequired ? 'required' : 'nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'media_asset_ids' => ['nullable', 'array'],
            'media_asset_ids.*' => ['integer', Rule::exists('media_assets', 'id')],
            'release_windows' => ['nullable', 'array'],
            'release_windows.*.audience' => ['required_with:release_windows', Rule::in(VisibilityAudience::values())],
            'release_windows.*.starts_at' => ['nullable', 'date'],
            'release_windows.*.ends_at' => ['nullable', 'date'],
            'release_windows.*.country_codes' => ['nullable', 'array'],
            'release_windows.*.country_codes.*' => ['string', 'size:2'],
        ];

        if ($request->filled('type') && $type = ContentType::tryFrom((string) $request->input('type'))) {
            $rules = [
                ...$rules,
                ...$this->forms->validationRules($type),
            ];
        }

        $payload = $this->normalizePayload($request->validate($rules));

        if (($payload['type'] ?? null) === ContentType::Poll->value && count($payload['metadata']['options'] ?? []) < 2) {
            throw ValidationException::withMessages([
                'metadata.options' => 'Polls require at least two options.',
            ]);
        }

        return $payload;
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
            'scheduled_at' => $content->scheduled_at?->timezone(self::SCHEDULING_TIMEZONE)->toISOString(),
            'preview_url' => route('admin.editorial.preview', $content),
        ];
    }

    private function normalizePayload(array $payload): array
    {
        if (isset($payload['metadata'])) {
            $payload['metadata'] = $this->cleanMetadata($payload['metadata']);
            $payload['metadata'] = $this->approvedMetadataForType(
                $payload['metadata'],
                (string) ($payload['type'] ?? '')
            );
        }

        if (array_key_exists('scheduled_at', $payload) && $payload['scheduled_at'] !== null) {
            $payload['scheduled_at'] = CarbonImmutable::parse(
                $payload['scheduled_at'],
                self::SCHEDULING_TIMEZONE
            )->setTimezone(self::STORAGE_TIMEZONE);
        }

        $payload['release_windows'] = collect($payload['release_windows'] ?? [])
            ->filter(fn (array $window): bool => filled($window['starts_at'] ?? null) || filled($window['ends_at'] ?? null))
            ->map(function (array $window): array {
                foreach (['starts_at', 'ends_at'] as $key) {
                    if (filled($window[$key] ?? null)) {
                        $window[$key] = CarbonImmutable::parse($window[$key], self::SCHEDULING_TIMEZONE)
                            ->setTimezone(self::STORAGE_TIMEZONE);
                    }
                }

                return $window;
            })
            ->values()
            ->all();

        $payload['media_assets'] = collect($payload['media_asset_ids'] ?? [])
            ->unique()
            ->values()
            ->map(fn (int|string $assetId, int $index): array => [
                'id' => (int) $assetId,
                'role' => $index === 0 ? 'primary' : 'supporting',
                'sort_order' => $index,
            ])
            ->all();

        unset($payload['media_asset_ids']);

        return $payload;
    }

    private function cleanMetadata(array $metadata): array
    {
        return collect($metadata)
            ->map(function (mixed $value): mixed {
                if (is_array($value)) {
                    return $this->cleanMetadata($value);
                }

                return is_string($value) ? trim($value) : $value;
            })
            ->reject(function (mixed $value): bool {
                if (is_array($value)) {
                    return $value === [];
                }

                return $value === null || $value === '';
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function approvedMetadataForType(array $metadata, string $type): array
    {
        return match ($type) {
            ContentType::Song->value => collect($metadata)
                ->only(['audio_asset_id', 'artwork_asset_id', 'release_date_member_view', 'release_date_open_view'])
                ->all(),
            ContentType::MusicalAlbum->value => [
                ...collect($metadata)
                    ->only(['album_artwork_asset_id', 'release_date_member_view', 'release_date_open_view'])
                    ->all(),
                'tracks' => collect($metadata['tracks'] ?? [])
                    ->map(fn (array $track): array => collect($track)
                        ->only(['track_name', 'track_audio_asset_id', 'release_date_member_view'])
                        ->all())
                    ->values()
                    ->all(),
            ],
            ContentType::MusicPlaylist->value => [
                ...collect($metadata)
                    ->only(['playlist_cover_asset_id'])
                    ->all(),
                'tracks' => collect($metadata['tracks'] ?? [])->filter()->values()->all(),
            ],
            default => $metadata,
        };
    }
}
