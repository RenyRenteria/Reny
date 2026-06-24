<?php

namespace App\Services\Admin;

use App\Enums\ContentType;
use App\Enums\MediaAssetType;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Services\Media\MediaLibraryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class MusicContentUploadService
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rulesFor(ContentType|string|null $type, bool $requireBaseFields = false): array
    {
        $baseRules = $requireBaseFields
            ? [
                'type' => ['required', Rule::in(ContentType::values())],
                'title' => ['required', 'string', 'max:160'],
            ]
            : [];
        $assetRule = ['nullable', 'integer', Rule::exists('media_assets', 'id')];

        return [
            ...$baseRules,
            ...match ($this->typeValue($type)) {
                ContentType::Song->value => [
                    'metadata.audio_asset_id' => $assetRule,
                    'metadata.artwork_asset_id' => $assetRule,
                    'metadata.release_date_member_view' => ['required', 'date'],
                    'metadata.release_date_open_view' => ['required', 'date', 'after_or_equal:metadata.release_date_member_view'],
                    'audio_file' => ['nullable', 'file', 'mimes:mp3,wav', 'max:102400'],
                    'artwork' => ['nullable', 'file', 'mimes:jpg', 'max:20480'],
                ],
                ContentType::MusicalAlbum->value => [
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
                ContentType::MusicPlaylist->value => [
                    'metadata.playlist_cover_asset_id' => $assetRule,
                    'metadata.tracks' => ['required', 'array', 'min:1'],
                    'metadata.tracks.*' => ['required', 'string', 'max:80'],
                    'playlist_cover' => ['nullable', 'file', 'mimes:jpg', 'max:20480', 'dimensions:ratio=1/1'],
                ],
                default => [],
            },
        ];
    }

    public function isMusicType(ContentType|string|null $type): bool
    {
        return in_array($this->typeValue($type), [
            ContentType::Song->value,
            ContentType::MusicalAlbum->value,
            ContentType::MusicPlaylist->value,
        ], true);
    }

    public function addValidationErrors(Validator $validator, Request $request, array $payload, ContentType|string|null $type): void
    {
        foreach ($this->validationErrors($request, $payload, $type) as $field => $message) {
            $validator->errors()->add($field, $message);
        }
    }

    public function validate(Request $request, array $payload, ContentType|string|null $type): void
    {
        $errors = $this->validationErrors($request, $payload, $type);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeMetadataForType(array $metadata, ContentType|string|null $type): array
    {
        $metadata = $this->pruneEmptyValues($metadata);

        return match ($this->typeValue($type)) {
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
    public function payloadWithReleaseWindows(array $payload, ContentType|string|null $type): array
    {
        if (! in_array($this->typeValue($type), [ContentType::Song->value, ContentType::MusicalAlbum->value], true)) {
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
    public function payloadWithUploads(
        Request $request,
        MediaLibraryService $library,
        array $payload,
        ?EditorialContent $content = null
    ): array {
        $type = $this->typeValue($payload['type'] ?? $content?->type);
        $payload = match ($type) {
            ContentType::Song->value => $this->songPayloadWithUploads($request, $library, $payload),
            ContentType::MusicalAlbum->value => $this->albumPayloadWithUploads($request, $library, $payload),
            ContentType::MusicPlaylist->value => $this->playlistPayloadWithUploads($request, $library, $payload),
            default => $payload,
        };

        return $this->isMusicType($type)
            ? Arr::except($payload, ['audio_file', 'artwork', 'album_artwork', 'track_audio_files', 'playlist_cover'])
            : $payload;
    }

    /**
     * @return array<string, string>
     */
    private function validationErrors(Request $request, array $payload, ContentType|string|null $type): array
    {
        $errors = [];

        if ($this->typeValue($type) === ContentType::Song->value) {
            if (blank(data_get($payload, 'metadata.audio_asset_id')) && ! $this->uploadedFile($request, 'audio_file')) {
                $errors['audio_file'] = 'Audio file is required for songs.';
            }

            if (blank(data_get($payload, 'metadata.artwork_asset_id')) && ! $this->uploadedFile($request, 'artwork')) {
                $errors['artwork'] = 'Artwork is required for songs.';
            }
        }

        if ($this->typeValue($type) === ContentType::MusicalAlbum->value) {
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
                        // The base date rules report invalid date formats.
                    }
                }
            }
        }

        if ($this->typeValue($type) === ContentType::MusicPlaylist->value) {
            if (blank(data_get($payload, 'metadata.playlist_cover_asset_id')) && ! $this->uploadedFile($request, 'playlist_cover')) {
                $errors['playlist_cover'] = 'Playlist cover is required.';
            }

            foreach (data_get($payload, 'metadata.tracks', []) as $index => $reference) {
                if (! is_string($reference) || ! $this->playlistTrackReferenceExists($reference)) {
                    $errors["metadata.tracks.{$index}"] = 'Select an existing single or album track.';
                }
            }
        }

        return $errors;
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

        $payload['metadata'] = $this->normalizeMetadataForType($metadata, ContentType::Song);
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

        $payload['metadata'] = $this->normalizeMetadataForType($metadata, ContentType::MusicalAlbum);
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

        $payload['metadata'] = $this->normalizeMetadataForType($metadata, ContentType::MusicPlaylist);
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

    private function typeValue(ContentType|string|null $type): ?string
    {
        return $type instanceof ContentType ? $type->value : $type;
    }
}
