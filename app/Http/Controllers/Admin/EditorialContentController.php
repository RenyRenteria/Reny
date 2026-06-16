<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\MediaAssetType;
use App\Enums\TaxonomyType;
use App\Enums\VisibilityAudience;
use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\Taxonomy;
use App\Services\EditorialWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EditorialContentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $contents = EditorialContent::query()
            ->with(['createdBy', 'mediaAssets', 'releaseWindows'])
            ->when(
                in_array($status, EditorialStatus::values(), true),
                fn ($query) => $query->where('status', $status)
            )
            ->latest()
            ->limit(50)
            ->get();

        return view('admin.content.index', [
            'contents' => $contents,
            'contentTypes' => ContentType::cases(),
            'statuses' => EditorialStatus::cases(),
            'activeStatus' => in_array($status, EditorialStatus::values(), true) ? $status : null,
            'timezone' => config('admin.publishing_timezone', 'America/Panama'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.content.form', $this->formData($request));
    }

    public function store(Request $request, EditorialWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        return $this->persist($request, $workflow);
    }

    public function edit(Request $request, EditorialContent $content): View
    {
        $content->load(['mediaAssets', 'taxonomies', 'releaseWindows']);

        return view('admin.content.form', $this->formData($request, $content));
    }

    public function update(
        Request $request,
        EditorialWorkflowService $workflow,
        EditorialContent $content
    ): JsonResponse|RedirectResponse {
        return $this->persist($request, $workflow, $content);
    }

    public function preview(EditorialContent $content): Response
    {
        $content->load(['mediaAssets', 'taxonomies', 'releaseWindows', 'createdBy', 'updatedBy']);

        return response()->view('admin.content.preview', [
            'content' => $content,
            'timezone' => config('admin.publishing_timezone', 'America/Panama'),
        ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }

    private function persist(
        Request $request,
        EditorialWorkflowService $workflow,
        ?EditorialContent $content = null
    ): JsonResponse|RedirectResponse {
        $payload = $this->validatedPayload($request);
        $action = Arr::pull($payload, 'action');
        $mediaAssetIds = Arr::pull($payload, 'media_asset_ids', []);
        $taxonomyIds = Arr::pull($payload, 'taxonomy_ids', []);

        if (in_array($action, ['publish', 'schedule'], true) && ! $request->user()->canPublishContent()) {
            abort(403);
        }

        $isNewContent = $content === null;

        $content = match ($action) {
            'publish' => $content
                ? $workflow->publish($request->user(), $content, $payload)
                : $workflow->publishNew($request->user(), $payload),
            'schedule' => $this->scheduleContent($request, $workflow, $payload, $content),
            default => $content
                ? $workflow->updateDraft($request->user(), $content, $payload)
                : $workflow->createDraft($request->user(), $payload),
        };

        $this->syncRelations($content, $mediaAssetIds, $taxonomyIds);

        $message = match ($action) {
            'publish' => sprintf('Content "%s" approved and published.', $content->title),
            'schedule' => sprintf('Content "%s" scheduled in Panama time.', $content->title),
            default => sprintf('Draft "%s" saved for approval.', $content->title),
        };

        if ($request->expectsJson()) {
            return response()->json($this->responsePayload($content, $message), $isNewContent ? 201 : 200);
        }

        return redirect()
            ->route('admin.content.edit', $content)
            ->with('status', $message);
    }

    private function scheduleContent(
        Request $request,
        EditorialWorkflowService $workflow,
        array $payload,
        ?EditorialContent $content = null
    ): EditorialContent {
        $content = $content
            ? $workflow->updateDraft($request->user(), $content, $payload)
            : $workflow->createDraft($request->user(), $payload);

        return $workflow->schedule(
            $request->user(),
            $content,
            $payload['scheduled_at'],
            $payload['release_windows'] ?? []
        );
    }

    private function validatedPayload(Request $request): array
    {
        $input = $this->normalizedInput($request->all());
        $type = (string) ($input['type'] ?? ContentType::Post->value);

        $validator = Validator::make($input, [
            'action' => ['required', Rule::in(['draft', 'publish', 'schedule'])],
            'type' => ['required', Rule::in(ContentType::values())],
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'visibility' => ['required', Rule::in(VisibilityAudience::values())],
            'purchase_key' => ['nullable', 'string', 'max:120'],
            'scheduled_at' => ['nullable', 'date', 'required_if:action,schedule'],
            'metadata' => ['nullable', 'array'],
            'release_windows' => ['nullable', 'array', 'max:4'],
            'release_windows.*.audience' => ['required_with:release_windows', Rule::in(VisibilityAudience::values())],
            'release_windows.*.starts_at' => ['nullable', 'date'],
            'release_windows.*.ends_at' => ['nullable', 'date'],
            'release_windows.*.country_codes' => ['nullable', 'array'],
            'release_windows.*.country_codes.*' => ['string', 'size:2'],
            'media_asset_ids' => ['nullable', 'array'],
            'media_asset_ids.*' => ['integer', Rule::exists('media_assets', 'id')],
            'taxonomy_ids' => ['nullable', 'array'],
            'taxonomy_ids.*' => ['integer', Rule::exists('taxonomies', 'id')],
            ...$this->metadataRulesFor($type),
        ]);

        $payload = $validator->validate();
        $payload['metadata'] = $this->pruneEmptyValues($payload['metadata'] ?? []);
        $payload['release_windows'] = $this->pruneEmptyValues($payload['release_windows'] ?? []);

        return $payload;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function metadataRulesFor(string $type): array
    {
        $assetRule = ['nullable', 'integer', Rule::exists('media_assets', 'id')];

        return match ($type) {
            ContentType::Song->value => [
                'metadata.duration_seconds' => ['required', 'integer', 'min:1', 'max:7200'],
                'metadata.release_date' => ['required', 'date'],
                'metadata.audio_asset_id' => $assetRule,
                'metadata.cover_asset_id' => $assetRule,
                'metadata.lyrics' => ['nullable', 'string'],
                'metadata.credits' => ['required', 'string', 'max:2000'],
                'metadata.preview_visibility' => ['required', Rule::in(VisibilityAudience::values())],
                'metadata.full_visibility' => ['required', Rule::in(VisibilityAudience::values())],
            ],
            ContentType::MusicalAlbum->value, ContentType::DeluxeAlbum->value => [
                'metadata.tracklist' => ['required', 'string', 'max:8000'],
                'metadata.narrative' => ['required', 'string', 'max:8000'],
                'metadata.cover_asset_id' => $assetRule,
                'metadata.price_cents' => ['nullable', 'integer', 'min:0'],
                'metadata.release_cycle' => ['required', 'string', 'max:160'],
            ],
            ContentType::Video->value => [
                'metadata.youtube_url' => ['required', 'url', 'max:500'],
                'metadata.thumbnail_asset_id' => $assetRule,
                'metadata.category' => ['required', 'string', 'max:120'],
                'metadata.access_tier' => ['required', Rule::in(VisibilityAudience::values())],
                'metadata.playlist' => ['nullable', 'string', 'max:160'],
            ],
            ContentType::Photo->value => [
                'metadata.image_asset_id' => $assetRule,
                'metadata.caption' => ['required', 'string', 'max:500'],
                'metadata.location' => ['nullable', 'string', 'max:160'],
                'metadata.tags' => ['nullable', 'string', 'max:500'],
            ],
            ContentType::Gallery->value => [
                'metadata.image_count' => ['required', 'integer', 'min:1', 'max:200'],
                'metadata.caption' => ['nullable', 'string', 'max:500'],
                'metadata.location' => ['nullable', 'string', 'max:160'],
                'metadata.tags' => ['nullable', 'string', 'max:500'],
            ],
            ContentType::Post->value => [
                'body' => ['required', 'string'],
                'metadata.links' => ['nullable', 'string', 'max:2000'],
                'metadata.comments_enabled' => ['nullable', 'boolean'],
                'metadata.is_pinned' => ['nullable', 'boolean'],
            ],
            ContentType::Poll->value => [
                'metadata.question' => ['required', 'string', 'max:220'],
                'metadata.options' => ['required', 'array', 'min:2', 'max:8'],
                'metadata.options.*' => ['required', 'string', 'max:120'],
                'metadata.closes_at' => ['nullable', 'date'],
                'metadata.eligibility' => ['required', Rule::in(VisibilityAudience::values())],
                'metadata.results_visibility' => ['required', Rule::in(['public', 'private'])],
            ],
            ContentType::Product->value => [
                'metadata.product_kind' => ['required', Rule::in(['digital', 'physical', 'subscription', 'drop', 'bundle'])],
                'metadata.sku' => ['nullable', 'string', 'max:120'],
                'metadata.price_cents' => ['nullable', 'integer', 'min:0'],
                'metadata.inventory' => ['nullable', 'integer', 'min:0'],
                'metadata.fulfillment_note' => ['nullable', 'string', 'max:1000'],
            ],
            ContentType::Event->value => [
                'metadata.event_kind' => ['required', Rule::in(['physical', 'digital', 'listening_session'])],
                'metadata.starts_at' => ['required', 'date'],
                'metadata.location' => ['nullable', 'string', 'max:180'],
                'metadata.inventory' => ['nullable', 'integer', 'min:0'],
                'metadata.price_cents' => ['nullable', 'integer', 'min:0'],
                'metadata.ticketing_mode' => ['required', Rule::in(['rsvp', 'ticket'])],
            ],
            ContentType::Drop->value => [
                'metadata.drop_kind' => ['required', Rule::in(['product', 'content', 'bundle'])],
                'metadata.opens_at' => ['required', 'date'],
                'metadata.closes_at' => ['nullable', 'date', 'after_or_equal:metadata.opens_at'],
                'metadata.inventory' => ['nullable', 'integer', 'min:0'],
            ],
            ContentType::Exclusive->value => [
                'metadata.exclusive_kind' => ['required', Rule::in(['audio', 'video', 'photo', 'post', 'download'])],
                'metadata.access_note' => ['required', 'string', 'max:1000'],
                'metadata.expires_at' => ['nullable', 'date'],
            ],
            default => [],
        };
    }

    private function syncRelations(EditorialContent $content, array $mediaAssetIds, array $taxonomyIds): void
    {
        $mediaSync = collect($mediaAssetIds)
            ->filter()
            ->unique()
            ->values()
            ->mapWithKeys(fn (int|string $id, int $index): array => [
                (int) $id => [
                    'role' => 'primary',
                    'sort_order' => $index + 1,
                    'metadata' => null,
                ],
            ])
            ->all();

        $content->mediaAssets()->sync($mediaSync);
        $content->taxonomies()->sync(collect($taxonomyIds)->filter()->unique()->values()->all());
    }

    private function formData(Request $request, ?EditorialContent $content = null): array
    {
        $contentType = $request->old(
            'type',
            $content?->type->value ?? $request->query('type', ContentType::Post->value)
        );

        if (! in_array($contentType, ContentType::values(), true)) {
            $contentType = ContentType::Post->value;
        }

        return [
            'content' => $content,
            'contentTypes' => ContentType::cases(),
            'visibilityAudiences' => VisibilityAudience::cases(),
            'mediaTypes' => MediaAssetType::cases(),
            'taxonomyTypes' => TaxonomyType::cases(),
            'selectedType' => $contentType,
            'mediaAssets' => MediaAsset::query()->ready()->latest()->limit(100)->get(),
            'taxonomies' => Taxonomy::query()->orderBy('type')->orderBy('name')->get(),
            'timezone' => config('admin.publishing_timezone', 'America/Panama'),
        ];
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
            'scheduled_at' => $content->scheduled_at?->toISOString(),
            'preview_url' => route('admin.content.preview', $content),
        ];
    }

    private function pruneEmptyValues(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return collect($value)
            ->map(fn (mixed $item): mixed => $this->pruneEmptyValues($item))
            ->reject(fn (mixed $item): bool => $item === null || $item === '' || $item === [])
            ->all();
    }

    private function normalizedInput(array $input): array
    {
        $input['release_windows'] = collect($input['release_windows'] ?? [])
            ->filter(fn ($window): bool => is_array($window)
                && collect($window)->flatten()->filter(fn ($value): bool => $value !== null && $value !== '')->isNotEmpty())
            ->values()
            ->all();

        if (isset($input['metadata']['options']) && is_array($input['metadata']['options'])) {
            $input['metadata']['options'] = collect($input['metadata']['options'])
                ->filter(fn ($value): bool => $value !== null && $value !== '')
                ->values()
                ->all();
        }

        return $input;
    }
}
