<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Enums\MediaAssetType;
use App\Enums\VisibilityAudience;
use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Services\EditorialWorkflowService;
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaUploadException;
use App\Support\EditorialContentForms;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EditorialActionController extends Controller
{
    private const SCHEDULING_TIMEZONE = 'America/Panama';

    private const STORAGE_TIMEZONE = 'UTC';

    public function __construct(private readonly EditorialContentForms $forms) {}

    public function saveDraft(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library
    ): JsonResponse|RedirectResponse {
        $payload = $this->validatedPayload($request);

        try {
            $payload = $this->payloadWithMusicUploads($request, $library, $payload);
        } catch (MediaUploadException $exception) {
            return $this->mediaUploadFailure($request, $exception);
        }

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
        EditorialWorkflowService $workflow,
        MediaLibraryService $library
    ): JsonResponse|RedirectResponse {
        $payload = $this->validatedPayload($request, true, false, $content);

        try {
            $payload = $this->payloadWithMusicUploads($request, $library, $payload, $content);
        } catch (MediaUploadException $exception) {
            return $this->mediaUploadFailure($request, $exception);
        }

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

    public function publish(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library
    ): JsonResponse|RedirectResponse {
        $payload = $this->validatedPayload($request, true);

        try {
            $payload = $this->payloadWithMusicUploads($request, $library, $payload);
        } catch (MediaUploadException $exception) {
            return $this->mediaUploadFailure($request, $exception);
        }

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

    public function schedule(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library
    ): JsonResponse|RedirectResponse {
        $payload = $this->validatedPayload($request, true, scheduledAtRequired: true);
        $scheduledAt = $payload['scheduled_at'];
        $releaseWindows = $payload['release_windows'] ?? [];

        try {
            $payload = $this->payloadWithMusicUploads($request, $library, $payload);
        } catch (MediaUploadException $exception) {
            return $this->mediaUploadFailure($request, $exception);
        }

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
        bool $scheduledAtRequired = false,
        ?EditorialContent $content = null
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

        $type = $this->contentType($request, $content);

        if ($type instanceof ContentType) {
            $rules = [
                ...$rules,
                ...$this->validationRulesFor($type),
            ];
        }

        $payload = $this->normalizePayload($request->validate($rules));

        $this->validateMusicPayload($request, $payload, $type);

        if (($payload['type'] ?? null) === ContentType::Poll->value && count($payload['metadata']['options'] ?? []) < 2) {
            throw ValidationException::withMessages([
                'metadata.options' => 'Polls require at least two options.',
            ]);
        }

        return $this->payloadWithMusicReleaseWindows($payload, $type);
    }

    private function contentType(Request $request, ?EditorialContent $content = null): ?ContentType
    {
        if ($request->filled('type')) {
            return ContentType::tryFrom((string) $request->input('type'));
        }

        return $content?->type instanceof ContentType
            ? $content->type
            : ContentType::tryFrom((string) $content?->type);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function validationRulesFor(ContentType $type): array
    {
        $assetRule = ['nullable', 'integer', Rule::exists('media_assets', 'id')];

        return match ($type) {
            ContentType::Song => [
                'type' => ['required', Rule::in(ContentType::values())],
                'title' => ['required', 'string', 'max:160'],
                'metadata.audio_asset_id' => $assetRule,
                'metadata.artwork_asset_id' => $assetRule,
                'metadata.release_date_member_view' => ['required', 'date'],
                'metadata.release_date_open_view' => ['required', 'date', 'after_or_equal:metadata.release_date_member_view'],
                'audio_file' => ['nullable', 'file', 'mimes:mp3,wav', 'max:102400'],
                'artwork' => ['nullable', 'file', 'mimes:jpg', 'max:20480'],
            ],
            ContentType::MusicalAlbum => [
                'type' => ['required', Rule::in(ContentType::values())],
                'title' => ['required', 'string', 'max:160'],
                'metadata.album_artwork_asset_id' => $assetRule,
                'metadata.release_date_member_view' => ['required', 'date'],
                'metadata.release_date_open_view' => ['required', 'date', 'after_or_equal:metadata.release_date_member_view'],
                'metadata.tracks' => ['required', 'array', 'min:1'],
                'metadata.tracks.*.track_name' => ['required', 'string', 'max:160'],
                'metadata.tracks.*.track_audio_asset_id' => $assetRule,
                'metadata.tracks.*.release_date_member_view' => ['nullable', 'date'],
                'album_artwork' => ['nullable', 'file', 'mimes:jpg', 'max:20480'],
                'track_audio_files' => ['nullable', 'array'],
                'track_audio_files.*' => ['nullable', 'file', 'mimes:mp3,wav', 'max:102400'],
            ],
            ContentType::MusicPlaylist => [
                'type' => ['required', Rule::in(ContentType::values())],
                'title' => ['required', 'string', 'max:160'],
                'metadata.playlist_cover_asset_id' => $assetRule,
                'metadata.tracks' => ['required', 'array', 'min:1'],
                'metadata.tracks.*' => ['required', 'string', 'max:80'],
                'playlist_cover' => ['nullable', 'file', 'mimes:jpg', 'max:20480', 'dimensions:ratio=1/1'],
            ],
            default => $this->forms->validationRules($type),
        };
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

    private function validateMusicPayload(Request $request, array $payload, ?ContentType $type): void
    {
        $errors = [];

        if ($type === ContentType::Song) {
            if (blank(data_get($payload, 'metadata.audio_asset_id')) && ! $this->uploadedFile($request, 'audio_file')) {
                $errors['audio_file'] = 'Audio file is required for songs.';
            }

            if (blank(data_get($payload, 'metadata.artwork_asset_id')) && ! $this->uploadedFile($request, 'artwork')) {
                $errors['artwork'] = 'Artwork is required for songs.';
            }
        }

        if ($type === ContentType::MusicalAlbum) {
            if (blank(data_get($payload, 'metadata.album_artwork_asset_id')) && ! $this->uploadedFile($request, 'album_artwork')) {
                $errors['album_artwork'] = 'Album artwork is required.';
            }

            $albumRelease = data_get($payload, 'metadata.release_date_member_view');

            foreach (data_get($payload, 'metadata.tracks', []) as $index => $track) {
                if (blank(data_get($track, 'track_audio_asset_id')) && ! $this->uploadedFile($request, "track_audio_files.{$index}")) {
                    $errors["track_audio_files.{$index}"] = 'Track audio file is required.';
                }

                $trackRelease = data_get($track, 'release_date_member_view');

                if (filled($albumRelease) && filled($trackRelease)) {
                    try {
                        if (CarbonImmutable::parse($trackRelease)->gt(CarbonImmutable::parse($albumRelease))) {
                            $errors["metadata.tracks.{$index}.release_date_member_view"] = 'Track release date must be before or equal to the album member release date.';
                        }
                    } catch (\Throwable) {
                    }
                }
            }
        }

        if ($type === ContentType::MusicPlaylist) {
            if (blank(data_get($payload, 'metadata.playlist_cover_asset_id')) && ! $this->uploadedFile($request, 'playlist_cover')) {
                $errors['playlist_cover'] = 'Playlist cover is required.';
            }

            foreach (data_get($payload, 'metadata.tracks', []) as $index => $reference) {
                if (! is_string($reference) || ! $this->playlistTrackReferenceExists($reference)) {
                    $errors["metadata.tracks.{$index}"] = 'Select an existing single or album track.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function payloadWithMusicReleaseWindows(array $payload, ?ContentType $type): array
    {
        if (! in_array($type, [ContentType::Song, ContentType::MusicalAlbum], true)) {
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

    private function payloadWithMusicUploads(
        Request $request,
        MediaLibraryService $library,
        array $payload,
        ?EditorialContent $content = null
    ): array {
        $type = $payload['type']
            ?? ($content?->type instanceof ContentType ? $content->type->value : $content?->type);

        return match ($type) {
            ContentType::Song->value => $this->songPayloadWithUploads($request, $library, $payload),
            ContentType::MusicalAlbum->value => $this->albumPayloadWithUploads($request, $library, $payload),
            ContentType::MusicPlaylist->value => $this->playlistPayloadWithUploads($request, $library, $payload),
            default => $payload,
        };
    }

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

        $payload['metadata'] = $this->approvedMetadataForType($this->cleanMetadata($metadata), ContentType::Song->value);
        $payload['media_assets'] = $this->musicMediaAssets([
            ['id' => $payload['metadata']['artwork_asset_id'] ?? null, 'role' => 'artwork'],
            ['id' => $payload['metadata']['audio_asset_id'] ?? null, 'role' => 'audio'],
        ]);

        return $payload;
    }

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

        $metadata['tracks'] = collect($metadata['tracks'] ?? [])
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

        $payload['metadata'] = $this->approvedMetadataForType($this->cleanMetadata($metadata), ContentType::MusicalAlbum->value);
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

        $payload['metadata'] = $this->approvedMetadataForType($this->cleanMetadata($metadata), ContentType::MusicPlaylist->value);
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

    private function mediaUploadFailure(Request $request, MediaUploadException $exception): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return back()->withErrors(['media' => $exception->getMessage()])->withInput();
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
