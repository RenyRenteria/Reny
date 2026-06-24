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
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaUploadException;
use App\Support\AdminCmsSections;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EditorialContentController extends Controller
{
    private const int ALBUM_MAX_TRACKS = 30;

    private const int MUSIC_AUDIO_MAX_KB = 51200;

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
        $validated = Validator::make($request->all(), [
            'album_title' => ['nullable', 'string', 'max:160'],
            'track_name' => ['nullable', 'string', 'max:160'],
            'track_index' => ['nullable', 'integer', 'min:0', 'max:'.(self::ALBUM_MAX_TRACKS - 1)],
            'track_audio_file' => ['required', 'file', 'mimes:mp3,wav', 'max:'.self::MUSIC_AUDIO_MAX_KB],
        ], [
            'track_audio_file.max' => 'Each track audio file must be 50MB or less.',
            'track_audio_file.mimes' => 'Track audio files must be MP3 or WAV.',
            'track_index.max' => 'Albums can have up to 30 tracks.',
        ])->validate();

        $audio = $this->uploadedFile($request, 'track_audio_file');

        if (! $audio) {
            return response()->json(['message' => 'Track audio file is required.'], 422);
        }

        $albumTitle = trim((string) ($validated['album_title'] ?? 'Album'));
        $trackName = trim((string) ($validated['track_name'] ?? 'track'));

        try {
            $asset = $library->storeUploads($request->user(), [
                'type' => MediaAssetType::Audio->value,
                'title' => ($albumTitle !== '' ? $albumTitle : 'Album').' - '.($trackName !== '' ? $trackName : 'track').' audio',
                'is_public' => true,
            ], [$audio])->first();
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
        abort_unless(in_array($content->type, [
            ContentType::Song,
            ContentType::MusicalAlbum,
            ContentType::MusicPlaylist,
        ], true), 404);

        $title = $content->title;
        $content->delete();

        return redirect()
            ->route('admin.site-editor.show', ['page' => 'music'])
            ->with('status', sprintf('"%s" eliminado de Music.', $title));
    }

    public function preview(EditorialContent $content): Response
    {
        $content->load(['mediaAssets', 'taxonomies', 'releaseWindows', 'createdBy', 'updatedBy']);

        return response()->view('admin.content.preview', [
            'content' => $content,
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
        $payload = $this->validatedPayload($request);
        $action = Arr::pull($payload, 'action');
        $mediaAssetIds = Arr::pull($payload, 'media_asset_ids', []);
        $taxonomyIds = Arr::pull($payload, 'taxonomy_ids', []);

        if (in_array($action, ['publish', 'schedule'], true) && ! $request->user()->canPublishContent()) {
            abort(403);
        }

        $isNewContent = $content === null;

        try {
            $payload = $this->payloadWithMusicUploads($request, $library, $payload, $content);
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
        ], [
            'audio_file.max' => 'Audio file must be 50MB or less.',
            'metadata.tracks.max' => 'Albums can have up to 30 tracks.',
            'track_audio_files.max' => 'Albums can have up to 30 track files.',
            'track_audio_files.*.max' => 'Each track audio file must be 50MB or less.',
        ]);

        $validator->after(function ($validator) use ($request, $input, $type): void {
            $this->validateMusicPayload($validator, $request, $input, $type);
        });

        $payload = $validator->validate();
        $payload['metadata'] = $this->normalizedMetadataForType($payload['metadata'] ?? [], $type);
        $payload['release_windows'] = $this->pruneEmptyValues($payload['release_windows'] ?? []);
        $payload = $this->payloadWithMusicReleaseWindows($payload, $type);

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
                'metadata.audio_asset_id' => $assetRule,
                'metadata.artwork_asset_id' => $assetRule,
                'metadata.release_date_member_view' => ['required', 'date'],
                'metadata.release_date_open_view' => ['required', 'date', 'after_or_equal:metadata.release_date_member_view'],
                'audio_file' => ['nullable', 'file', 'mimes:mp3,wav', 'max:'.self::MUSIC_AUDIO_MAX_KB],
                'artwork' => ['nullable', 'file', 'mimes:jpg', 'max:20480'],
            ],
            ContentType::MusicalAlbum->value => [
                'metadata.album_artwork_asset_id' => $assetRule,
                'metadata.release_date_member_view' => ['required', 'date'],
                'metadata.release_date_open_view' => ['required', 'date', 'after_or_equal:metadata.release_date_member_view'],
                'metadata.tracks' => ['required', 'array', 'min:1', 'max:'.self::ALBUM_MAX_TRACKS],
                'metadata.tracks.*.track_name' => ['required', 'string', 'max:160'],
                'metadata.tracks.*.track_audio_asset_id' => $assetRule,
                'metadata.tracks.*.release_date_member_view' => ['nullable', 'date'],
                'album_artwork' => ['nullable', 'file', 'mimes:jpg', 'max:20480'],
                'track_audio_files' => ['nullable', 'array', 'max:'.self::ALBUM_MAX_TRACKS],
                'track_audio_files.*' => ['nullable', 'file', 'mimes:mp3,wav', 'max:'.self::MUSIC_AUDIO_MAX_KB],
            ],
            ContentType::DeluxeAlbum->value => [
                'purchase_key' => ['required', 'string', 'max:120'],
                'metadata.package_title' => ['required', 'string', 'max:160'],
                'metadata.package_notes' => ['required', 'string'],
                'metadata.price' => ['nullable', 'numeric', 'min:0'],
            ],
            ContentType::MusicPlaylist->value => [
                'metadata.playlist_cover_asset_id' => $assetRule,
                'metadata.tracks' => ['required', 'array', 'min:1'],
                'metadata.tracks.*' => ['required', 'string', 'max:80'],
                'playlist_cover' => ['nullable', 'file', 'mimes:jpg', 'max:20480', 'dimensions:ratio=1/1'],
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

    private function validateMusicPayload($validator, Request $request, array $input, string $type): void
    {
        if ($type === ContentType::Song->value) {
            if (blank(data_get($input, 'metadata.audio_asset_id')) && ! $this->uploadedFile($request, 'audio_file')) {
                $validator->errors()->add('audio_file', 'Audio file is required for songs.');
            }

            if (blank(data_get($input, 'metadata.artwork_asset_id')) && ! $this->uploadedFile($request, 'artwork')) {
                $validator->errors()->add('artwork', 'Artwork is required for songs.');
            }

            return;
        }

        if ($type === ContentType::MusicalAlbum->value) {
            if (blank(data_get($input, 'metadata.album_artwork_asset_id')) && ! $this->uploadedFile($request, 'album_artwork')) {
                $validator->errors()->add('album_artwork', 'Album artwork is required.');
            }

            $albumRelease = data_get($input, 'metadata.release_date_member_view');

            foreach (data_get($input, 'metadata.tracks', []) as $index => $track) {
                if (blank(data_get($track, 'track_audio_asset_id')) && ! $this->uploadedFile($request, "track_audio_files.{$index}")) {
                    $validator->errors()->add("track_audio_files.{$index}", 'Track audio file is required.');
                }

                $trackRelease = data_get($track, 'release_date_member_view');

                if (filled($albumRelease) && filled($trackRelease)) {
                    try {
                        if (CarbonImmutable::parse($trackRelease)->gt(CarbonImmutable::parse($albumRelease))) {
                            $validator->errors()->add(
                                "metadata.tracks.{$index}.release_date_member_view",
                                'Track release date must be before or equal to the album member release date.'
                            );
                        }
                    } catch (\Throwable) {
                        // The base date rules will report invalid date formats.
                    }
                }
            }

            return;
        }

        if ($type !== ContentType::MusicPlaylist->value) {
            return;
        }

        if (blank(data_get($input, 'metadata.playlist_cover_asset_id')) && ! $this->uploadedFile($request, 'playlist_cover')) {
            $validator->errors()->add('playlist_cover', 'Playlist cover is required.');
        }

        foreach (data_get($input, 'metadata.tracks', []) as $index => $reference) {
            if (! is_string($reference) || ! $this->playlistTrackReferenceExists($reference)) {
                $validator->errors()->add("metadata.tracks.{$index}", 'Select an existing single or album track.');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedMetadataForType(array $metadata, string $type): array
    {
        $metadata = $this->pruneEmptyValues($metadata);

        return match ($type) {
            ContentType::Song->value => $this->pruneEmptyValues(Arr::only($metadata, [
                'audio_asset_id',
                'artwork_asset_id',
                'release_date_member_view',
                'release_date_open_view',
            ])),
            ContentType::MusicalAlbum->value => $this->pruneEmptyValues([
                ...Arr::only($metadata, [
                    'album_artwork_asset_id',
                    'release_date_member_view',
                    'release_date_open_view',
                ]),
                'tracks' => collect($metadata['tracks'] ?? [])
                    ->map(fn (array $track): array => $this->pruneEmptyValues(Arr::only($track, [
                        'track_name',
                        'track_audio_asset_id',
                        'release_date_member_view',
                    ])))
                    ->values()
                    ->all(),
            ]),
            ContentType::MusicPlaylist->value => $this->pruneEmptyValues([
                ...Arr::only($metadata, [
                    'playlist_cover_asset_id',
                ]),
                'tracks' => collect($metadata['tracks'] ?? [])->filter()->values()->all(),
            ]),
            default => $metadata,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadWithMusicReleaseWindows(array $payload, string $type): array
    {
        if (! in_array($type, [ContentType::Song->value, ContentType::MusicalAlbum->value], true)) {
            return $payload;
        }

        $memberRelease = data_get($payload, 'metadata.release_date_member_view');
        $openRelease = data_get($payload, 'metadata.release_date_open_view');

        if (blank($memberRelease) || blank($openRelease)) {
            return $payload;
        }

        $payload['visibility'] = VisibilityAudience::Open->value;
        $payload['release_windows'] = [
            [
                'audience' => VisibilityAudience::Member->value,
                'starts_at' => $memberRelease,
            ],
            [
                'audience' => VisibilityAudience::Open->value,
                'starts_at' => $openRelease,
            ],
        ];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadWithMusicUploads(
        Request $request,
        MediaLibraryService $library,
        array $payload,
        ?EditorialContent $content = null
    ): array {
        $type = $payload['type'] ?? $content?->type->value;

        if ($type === ContentType::Song->value) {
            return $this->songPayloadWithUploads($request, $library, $payload);
        }

        if ($type === ContentType::MusicalAlbum->value) {
            return $this->albumPayloadWithUploads($request, $library, $payload);
        }

        if ($type === ContentType::MusicPlaylist->value) {
            return $this->playlistPayloadWithUploads($request, $library, $payload);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function songPayloadWithUploads(Request $request, MediaLibraryService $library, array $payload): array
    {
        $metadata = $payload['metadata'] ?? [];

        if ($audio = $this->uploadedFile($request, 'audio_file')) {
            $asset = $library->storeUploads($request->user(), [
                'type' => MediaAssetType::Audio->value,
                'title' => $payload['title'].' audio',
                'is_public' => true,
            ], [$audio])->first();

            $metadata['audio_asset_id'] = $asset?->id;
        }

        if ($artwork = $this->uploadedFile($request, 'artwork')) {
            $asset = $library->storeUploads($request->user(), [
                'type' => MediaAssetType::Thumbnail->value,
                'title' => $payload['title'].' artwork',
                'alt_text' => $payload['title'].' artwork',
                'is_public' => true,
            ], [$artwork])->first();

            $metadata['artwork_asset_id'] = $asset?->id;
        }

        $payload['metadata'] = $this->normalizedMetadataForType($metadata, ContentType::Song->value);
        $payload['media_assets'] = $this->musicMediaAssets([
            ['id' => $payload['metadata']['artwork_asset_id'] ?? null, 'role' => 'artwork'],
            ['id' => $payload['metadata']['audio_asset_id'] ?? null, 'role' => 'audio'],
        ]);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function albumPayloadWithUploads(Request $request, MediaLibraryService $library, array $payload): array
    {
        $metadata = $payload['metadata'] ?? [];

        if ($artwork = $this->uploadedFile($request, 'album_artwork')) {
            $asset = $library->storeUploads($request->user(), [
                'type' => MediaAssetType::Thumbnail->value,
                'title' => $payload['title'].' album artwork',
                'alt_text' => $payload['title'].' album artwork',
                'is_public' => true,
            ], [$artwork])->first();

            $metadata['album_artwork_asset_id'] = $asset?->id;
        }

        $tracks = collect($metadata['tracks'] ?? [])
            ->map(function (array $track, int $index) use ($request, $library, $payload): array {
                if ($audio = $this->uploadedFile($request, "track_audio_files.{$index}")) {
                    $asset = $library->storeUploads($request->user(), [
                        'type' => MediaAssetType::Audio->value,
                        'title' => $payload['title'].' - '.($track['track_name'] ?? 'track').' audio',
                        'is_public' => true,
                    ], [$audio])->first();

                    $track['track_audio_asset_id'] = $asset?->id;
                }

                return $track;
            })
            ->values()
            ->all();

        $metadata['tracks'] = $tracks;
        $payload['metadata'] = $this->normalizedMetadataForType($metadata, ContentType::MusicalAlbum->value);
        $payload['media_assets'] = $this->musicMediaAssets([
            ['id' => $payload['metadata']['album_artwork_asset_id'] ?? null, 'role' => 'artwork'],
            ...collect($payload['metadata']['tracks'] ?? [])
                ->map(fn (array $track, int $index): array => [
                    'id' => $track['track_audio_asset_id'] ?? null,
                    'role' => 'track_audio',
                    'metadata' => ['track_index' => $index],
                ])
                ->all(),
        ]);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function playlistPayloadWithUploads(Request $request, MediaLibraryService $library, array $payload): array
    {
        $metadata = $payload['metadata'] ?? [];

        if ($cover = $this->uploadedFile($request, 'playlist_cover')) {
            $asset = $library->storeUploads($request->user(), [
                'type' => MediaAssetType::Thumbnail->value,
                'title' => $payload['title'].' playlist cover',
                'alt_text' => $payload['title'].' playlist cover',
                'is_public' => true,
            ], [$cover])->first();

            $metadata['playlist_cover_asset_id'] = $asset?->id;
        }

        $payload['metadata'] = $this->normalizedMetadataForType($metadata, ContentType::MusicPlaylist->value);
        $payload['media_assets'] = $this->musicMediaAssets([
            ['id' => $payload['metadata']['playlist_cover_asset_id'] ?? null, 'role' => 'cover'],
        ]);

        return $payload;
    }

    /**
     * @param  array<int, array{id:mixed,role:string,metadata?:array<string,mixed>}>  $assets
     * @return array<int, array{id:int,role:string,sort_order:int,metadata?:array<string,mixed>}>
     */
    private function musicMediaAssets(array $assets): array
    {
        return collect($assets)
            ->filter(fn (array $asset): bool => filled($asset['id'] ?? null))
            ->values()
            ->map(fn (array $asset, int $index): array => [
                'id' => (int) $asset['id'],
                'role' => $asset['role'],
                'sort_order' => $index,
                ...(isset($asset['metadata']) ? ['metadata' => $asset['metadata']] : []),
            ])
            ->all();
    }

    private function uploadedFile(Request $request, string $key): ?UploadedFile
    {
        $file = $request->file($key);

        return $file instanceof UploadedFile ? $file : null;
    }

    private function playlistTrackReferenceExists(string $reference): bool
    {
        $parts = explode(':', $reference);

        if (count($parts) < 2) {
            return false;
        }

        if ($parts[0] === 'song') {
            return EditorialContent::query()
                ->whereKey((int) $parts[1])
                ->where('type', ContentType::Song->value)
                ->exists();
        }

        if ($parts[0] !== 'album' || count($parts) !== 3) {
            return false;
        }

        $album = EditorialContent::query()
            ->whereKey((int) $parts[1])
            ->where('type', ContentType::MusicalAlbum->value)
            ->first();

        if (! $album) {
            return false;
        }

        $index = (int) $parts[2];
        $tracks = $album->metadata['tracks'] ?? [];

        return is_array($tracks) && filled(data_get($tracks, "{$index}.track_name"));
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
