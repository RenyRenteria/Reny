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
use App\Services\Admin\MusicContentUploadService;
use App\Services\Admin\VideoCatalogService;
use App\Services\Commerce\CommercePublicationValidator;
use App\Services\EditorialWorkflowService;
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaUploadException;
use App\Services\PublicCms\PayloadMediaResolver;
use App\Support\AdminCmsSections;
use App\Support\VideoCatalog;
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
    public function __construct(
        private readonly MusicContentUploadService $musicContent,
        private readonly VideoCatalogService $videoCatalog,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $activeSection = AdminCmsSections::normalize($request->query('section'));

        $contents = EditorialContent::query()
            ->with(['createdBy', 'mediaAssets', 'releaseWindows'])
            ->when(
                in_array($status, EditorialStatus::values(), true),
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                $activeSection !== null,
                fn ($query) => $query->whereIn('type', AdminCmsSections::typesFor($activeSection))
            )
            ->latest()
            ->limit(50)
            ->get();

        return view('admin.content.index', [
            'contents' => $contents,
            'contentTypes' => ContentType::cases(),
            'statuses' => EditorialStatus::cases(),
            'activeStatus' => in_array($status, EditorialStatus::values(), true) ? $status : null,
            'activeSection' => $activeSection,
            'timezone' => config('admin.publishing_timezone', 'America/Panama'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.content.form', $this->formData($request));
    }

    public function store(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library
    ): JsonResponse|RedirectResponse {
        return $this->persist($request, $workflow, $library);
    }

    public function edit(Request $request, EditorialContent $content): View
    {
        $content->load(['mediaAssets', 'taxonomies', 'releaseWindows']);

        return view('admin.content.form', $this->formData($request, $content));
    }

    public function update(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library,
        EditorialContent $content
    ): JsonResponse|RedirectResponse {
        return $this->persist($request, $workflow, $library, $content);
    }

    public function storeAlbumTrackAudio(Request $request, MediaLibraryService $library): JsonResponse
    {
        try {
            $asset = $this->musicContent->storeAlbumTrackAudio($request, $library);
        } catch (MediaUploadException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json([
            'message' => 'Track audio uploaded.',
            'asset' => [
                'id' => $asset?->id,
                'title' => $asset?->title,
                'filename' => $asset?->original_filename,
                'size_bytes' => $asset?->size_bytes,
            ],
        ], 201);
    }

    public function destroy(Request $request, EditorialContent $content): RedirectResponse
    {
        abort_unless($request->user()?->canPublishContent(), 403);
        abort_unless($content->status === EditorialStatus::Draft, 409, 'Published content must be archived instead of deleted.');

        $title = $content->title;
        $content->delete();

        return redirect()
            ->route($request->boolean('return_to_video_editor')
                ? 'admin.site-editor.show'
                : 'admin.content.index', $request->boolean('return_to_video_editor')
                ? ['page' => 'videos']
                : [])
            ->with('status', sprintf('Draft "%s" deleted.', $title));
    }

    public function archive(
        Request $request,
        EditorialContent $content,
        EditorialWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->archive($request->user(), $content);

        return back()->with('status', sprintf('Content "%s" archived and removed from public pages.', $content->title));
    }

    public function preview(Request $request, EditorialContent $content): Response
    {
        $audience = (string) $request->query('audience', VisibilityAudience::Open->value);

        if (! in_array($audience, VisibilityAudience::values(), true)) {
            $audience = VisibilityAudience::Open->value;
        }

        $content->load([
            'mediaAssets',
            'taxonomies',
            'releaseWindows',
            'createdBy',
            'updatedBy',
            'auditLogs' => fn ($query) => $query->with('actor:id,name,email')->latest('created_at'),
        ]);

        return response()->view('admin.content.preview', [
            'content' => $content,
            'previewAudience' => $audience,
            'previewAudiences' => VisibilityAudience::cases(),
            'timezone' => config('admin.publishing_timezone', 'America/Panama'),
        ])
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Cache-Control', 'no-store, private');
    }

    private function persist(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library,
        ?EditorialContent $content = null
    ): JsonResponse|RedirectResponse {
        $payload = $this->validatedPayload($request, $content);
        $action = Arr::pull($payload, 'action');
        $mediaAssetIds = Arr::pull($payload, 'media_asset_ids', []);
        $taxonomyIds = Arr::pull($payload, 'taxonomy_ids', []);

        if (in_array($action, ['publish', 'schedule'], true) && ! $request->user()->canPublishContent()) {
            abort(403);
        }

        $isNewContent = $content === null;

        try {
            $payload = $this->musicContent->payloadWithUploads($request, $library, $payload, $content);
        } catch (MediaUploadException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 503);
            }

            return back()->withErrors(['media' => $exception->getMessage()])->withInput();
        }

        $content = match ($action) {
            'publish' => $content
                ? $workflow->publish($request->user(), $content, $payload)
                : $workflow->publishNew($request->user(), $payload),
            'schedule' => $this->scheduleContent($request, $workflow, $payload, $content),
            default => $content
                ? $workflow->updateDraft($request->user(), $content, $payload)
                : $workflow->createDraft($request->user(), $payload),
        };

        if ($mediaAssetIds !== []) {
            $this->syncRelations($content, $mediaAssetIds, $taxonomyIds);
        } elseif ($taxonomyIds !== []) {
            $content->taxonomies()->sync(collect($taxonomyIds)->filter()->unique()->values()->all());
        }

        if ($content->type === ContentType::Video) {
            $this->videoCatalog->ensureSingleFeatured($content);
        }

        $message = match ($action) {
            'publish' => sprintf('Content "%s" approved and published.', $content->title),
            'schedule' => sprintf('Content "%s" scheduled in Panama time.', $content->title),
            default => sprintf('Draft "%s" saved for approval.', $content->title),
        };

        if ($request->expectsJson()) {
            return response()->json($this->responsePayload($content, $message), $isNewContent ? 201 : 200);
        }

        if ($request->boolean('return_to_music_editor')) {
            return redirect()
                ->route('admin.site-editor.show', ['page' => 'music'])
                ->with('status', $message);
        }

        if ($request->boolean('return_to_video_editor')) {
            return redirect()
                ->route('admin.site-editor.show', ['page' => 'videos'])
                ->with('status', $message);
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

    private function validatedPayload(Request $request, ?EditorialContent $content = null): array
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
            'metadata.currency' => ['nullable', 'string', 'size:3'],
            'metadata.inventory' => ['nullable', 'integer', 'min:0'],
            'metadata.is_active' => ['nullable', 'boolean'],
            'metadata.checkout_enabled' => ['nullable', 'boolean'],
            'metadata.availability_starts_at' => ['nullable', 'date'],
            'metadata.availability_ends_at' => ['nullable', 'date', 'after:metadata.availability_starts_at'],
            'metadata.available_from' => ['nullable', 'date'],
            'metadata.available_until' => ['nullable', 'date', 'after:metadata.available_from'],
            'metadata.action_type' => ['nullable', Rule::in(['buy', 'rsvp', 'link'])],
            'metadata.cta_label' => ['nullable', 'string', 'max:80'],
            'metadata.action_url' => ['nullable', 'string', 'max:2048'],
            'metadata.meta_title' => ['nullable', 'string', 'max:160'],
            'metadata.meta_description' => ['nullable', 'string', 'max:320'],
            'metadata.canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'metadata.og_title' => ['nullable', 'string', 'max:160'],
            'metadata.og_description' => ['nullable', 'string', 'max:320'],
            'metadata.og_image' => ['nullable', 'url:http,https', 'max:2048'],
            'metadata.twitter_card' => ['nullable', Rule::in(['summary', 'summary_large_image'])],
            'metadata.twitter_title' => ['nullable', 'string', 'max:160'],
            'metadata.twitter_description' => ['nullable', 'string', 'max:320'],
            'metadata.twitter_image' => ['nullable', 'url:http,https', 'max:2048'],
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
        ], [
            'audio_file.max' => 'Audio file must be 50MB or less.',
            'metadata.tracks.max' => 'Albums can have up to 30 tracks.',
            'track_audio_files.max' => 'Albums can have up to 30 track files.',
            'track_audio_files.*.max' => 'Each track audio file must be 50MB or less.',
        ]);

        $validator->after(function ($validator) use ($request, $input, $type): void {
            $this->musicContent->addValidationErrors($validator, $request, $input, $type);

            if ($type !== ContentType::Video->value) {
                return;
            }

            $url = (string) data_get($input, 'metadata.youtube_url', '');
            $group = VideoCatalog::groupFor((array) data_get($input, 'metadata', []));
            $media = app(PayloadMediaResolver::class);
            $validVideo = $media->youtubeId($url) !== null;
            $validPlaylist = $group === 'series' && $media->youtubePlaylistId($url) !== null;

            if (! $validVideo && ! $validPlaylist) {
                $validator->errors()->add(
                    'metadata.youtube_url',
                    'Enter a valid YouTube video or playlist URL.',
                );
            }
        });

        $payload = $validator->validate();
        $payload['metadata'] = $this->normalizedMetadataForType($payload['metadata'] ?? [], $type, $content);
        $payload['release_windows'] = $this->pruneEmptyValues($payload['release_windows'] ?? []);
        $payload = $this->musicContent->payloadWithReleaseWindows($payload, $type);

        if (in_array($payload['action'], ['publish', 'schedule'], true)) {
            $payload = app(CommercePublicationValidator::class)->prepareAndValidate($payload, $content);
        }

        return $payload;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function metadataRulesFor(string $type): array
    {
        if ($this->musicContent->isMusicType($type)) {
            return $this->musicContent->rulesFor($type);
        }

        $assetRule = ['nullable', 'integer', Rule::exists('media_assets', 'id')];

        return match ($type) {
            ContentType::DeluxeAlbum->value => [
                'purchase_key' => ['required', 'string', 'max:120'],
                'metadata.package_title' => ['required', 'string', 'max:160'],
                'metadata.package_notes' => ['required', 'string'],
                'metadata.price' => ['nullable', 'numeric', 'min:0'],
            ],
            ContentType::Video->value => [
                'metadata.youtube_url' => ['required', 'url', 'max:500'],
                'metadata.thumbnail_asset_id' => $assetRule,
                'metadata.category' => ['required', 'string', 'max:120'],
                'metadata.access_tier' => ['required', Rule::in(VisibilityAudience::values())],
                'metadata.playlist' => ['nullable', 'string', 'max:160'],
                'metadata.sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
                'metadata.is_featured' => ['nullable', 'boolean'],
                'metadata.featured_only' => ['nullable', 'boolean'],
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
                'metadata.currency' => ['nullable', 'string', 'size:3'],
                'metadata.inventory' => ['nullable', 'integer', 'min:0'],
                'metadata.checkout_enabled' => ['nullable', 'boolean'],
                'metadata.available_from' => ['nullable', 'date'],
                'metadata.available_until' => ['nullable', 'date', 'after:metadata.available_from'],
                'metadata.action_type' => ['nullable', Rule::in(['buy', 'link'])],
                'metadata.action_url' => ['nullable', 'string', 'max:500'],
                'metadata.cta_label' => ['nullable', 'string', 'max:80'],
                'metadata.fulfillment_note' => ['nullable', 'string', 'max:1000'],
            ],
            ContentType::Event->value => [
                'metadata.event_kind' => ['required', Rule::in(['physical', 'digital', 'listening_session'])],
                'metadata.starts_at' => ['required', 'date'],
                'metadata.location' => ['nullable', 'string', 'max:180'],
                'metadata.inventory' => ['nullable', 'integer', 'min:0'],
                'metadata.price_cents' => ['nullable', 'integer', 'min:0'],
                'metadata.currency' => ['nullable', 'string', 'size:3'],
                'metadata.checkout_enabled' => ['nullable', 'boolean'],
                'metadata.available_from' => ['nullable', 'date'],
                'metadata.available_until' => ['nullable', 'date', 'after:metadata.available_from'],
                'metadata.ticketing_mode' => ['required', Rule::in(['rsvp', 'ticket'])],
                'metadata.action_type' => ['nullable', Rule::in(['buy', 'rsvp', 'link'])],
                'metadata.action_url' => ['nullable', 'string', 'max:500'],
                'metadata.cta_label' => ['nullable', 'string', 'max:80'],
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

    /**
     * @return array<string, mixed>
     */
    private function normalizedMetadataForType(
        array $metadata,
        string $type,
        ?EditorialContent $content = null,
    ): array {
        if ($this->musicContent->isMusicType($type)) {
            return $this->musicContent->normalizeMetadataForType($metadata, $type);
        }

        $metadata = $this->pruneEmptyValues($metadata);

        return match ($type) {
            ContentType::Video->value => $this->videoCatalog->normalizeMetadata($metadata, $content),
            ContentType::Product->value => [
                ...$metadata,
                'currency' => strtoupper((string) ($metadata['currency'] ?? 'USD')),
                'checkout_enabled' => array_key_exists('checkout_enabled', $metadata)
                    ? filter_var($metadata['checkout_enabled'], FILTER_VALIDATE_BOOL)
                    : true,
                'action_type' => (string) ($metadata['action_type'] ?? 'buy'),
                'cta_label' => (string) ($metadata['cta_label'] ?? 'BUY NOW'),
            ],
            ContentType::Event->value => [
                ...$metadata,
                'currency' => strtoupper((string) ($metadata['currency'] ?? 'USD')),
                'checkout_enabled' => array_key_exists('checkout_enabled', $metadata)
                    ? filter_var($metadata['checkout_enabled'], FILTER_VALIDATE_BOOL)
                    : true,
                'action_type' => (string) ($metadata['action_type'] ?? (($metadata['ticketing_mode'] ?? 'rsvp') === 'rsvp' ? 'rsvp' : 'buy')),
                'cta_label' => (string) ($metadata['cta_label'] ?? (($metadata['ticketing_mode'] ?? 'rsvp') === 'rsvp' ? 'RSVP' : 'BUY TICKETS')),
            ],
            default => $metadata,
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
            'trackOptions' => $this->musicTrackOptions(),
            'taxonomies' => Taxonomy::query()->orderBy('type')->orderBy('name')->get(),
            'timezone' => config('admin.publishing_timezone', 'America/Panama'),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, group: string}>
     */
    private function musicTrackOptions(): array
    {
        return EditorialContent::query()
            ->whereIn('type', [ContentType::Song->value, ContentType::MusicalAlbum->value])
            ->latest()
            ->limit(100)
            ->get()
            ->flatMap(function (EditorialContent $content) {
                if ($content->type === ContentType::Song) {
                    return [[
                        'value' => 'song:'.$content->id,
                        'label' => $content->title,
                        'group' => 'Singles',
                    ]];
                }

                $tracks = collect($content->metadata['tracks'] ?? []);

                if ($tracks->isEmpty() && filled($content->metadata['tracklist'] ?? null)) {
                    $tracks = collect(preg_split('/\R/', (string) $content->metadata['tracklist']) ?: [])
                        ->map(fn (string $trackName): array => ['track_name' => trim($trackName)])
                        ->filter(fn (array $track): bool => filled($track['track_name']));
                }

                return $tracks
                    ->values()
                    ->map(fn (array $track, int $index): array => [
                        'value' => 'album:'.$content->id.':'.$index,
                        'label' => $content->title.' - '.($track['track_name'] ?? 'Track '.($index + 1)),
                        'group' => 'Album tracks',
                    ]);
            })
            ->values()
            ->all();
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
