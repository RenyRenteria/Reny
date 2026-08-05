<?php

namespace App\Services\Media;

use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\SitePageSetting;
use App\Models\User;
use App\Services\PublicCmsContentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MediaLibraryService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, MediaAsset>
     */
    public function storeUploads(User $actor, array $attributes, array $files): Collection
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($actor, $attributes, $files, &$storedPaths): Collection {
                $type = MediaAssetType::from($attributes['type']);

                if ($type === MediaAssetType::ShortVideo) {
                    throw new MediaUploadException('Short video uploads must use Mux direct upload.');
                }

                $isPublic = (bool) ($attributes['is_public'] ?? false);
                $disk = $isPublic ? config('media.public_disk', 'public') : config('media.private_disk', 'local');
                $directory = 'media/'.$type->value.'/'.now()->format('Y/m');

                return collect($files)->map(function (UploadedFile $file) use ($actor, $attributes, $type, $isPublic, $disk, $directory, &$storedPaths): MediaAsset {
                    $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
                    $filename = (string) Str::uuid().'.'.$extension;

                    try {
                        $path = Storage::disk($disk)->putFileAs($directory, $file, $filename);
                    } catch (Throwable $exception) {
                        throw new MediaUploadException('Upload failed because app-server storage is unavailable.', previous: $exception);
                    }

                    if (! is_string($path) || $path === '') {
                        throw new MediaUploadException('Upload failed because app-server storage is unavailable.');
                    }

                    $storedPaths[] = [$disk, $path];

                    $realPath = $file->getRealPath();

                    return MediaAsset::create([
                        'type' => $type->value,
                        'title' => $attributes['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                        'disk' => $disk,
                        'path' => $path,
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                        'extension' => $extension,
                        'size_bytes' => $file->getSize() ?: 0,
                        'checksum' => $realPath && is_file($realPath) ? hash_file('sha256', $realPath) : null,
                        'is_public' => $isPublic,
                        'alt_text' => $attributes['alt_text'] ?? null,
                        'duration_seconds' => $attributes['duration_seconds'] ?? null,
                        'processing_status' => MediaProcessingStatus::Ready->value,
                        'uploaded_by_id' => $actor->id,
                        'metadata' => $attributes['metadata'] ?? [],
                    ]);
                });
            });
        } catch (MediaUploadException $exception) {
            $this->deleteStoredPaths($storedPaths);

            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);
            report($exception);

            throw new MediaUploadException('Upload failed before media metadata could be saved.', previous: $exception);
        }
    }

    public function createMuxDirectUpload(User $actor, array $attributes, MuxVideoService $mux): array
    {
        $uuid = (string) Str::uuid();
        $isPublic = (bool) ($attributes['is_public'] ?? false);
        $upload = $mux->createDirectUpload($uuid, $isPublic);

        $asset = MediaAsset::create([
            'uuid' => $uuid,
            'type' => MediaAssetType::ShortVideo->value,
            'title' => $attributes['title'] ?? pathinfo($attributes['original_filename'], PATHINFO_FILENAME),
            'disk' => 'mux',
            'path' => 'mux://uploads/'.$upload['id'],
            'original_filename' => $attributes['original_filename'],
            'mime_type' => $attributes['mime_type'] ?? null,
            'extension' => strtolower(pathinfo($attributes['original_filename'], PATHINFO_EXTENSION)),
            'size_bytes' => $attributes['size_bytes'] ?? 0,
            'is_public' => $isPublic,
            'duration_seconds' => $attributes['duration_seconds'] ?? null,
            'processing_status' => MediaProcessingStatus::Pending->value,
            'mux_upload_id' => $upload['id'],
            'mux_status' => $upload['status'] ?? 'waiting',
            'uploaded_by_id' => $actor->id,
            'metadata' => [
                ...($attributes['metadata'] ?? []),
                'mux_passthrough' => $uuid,
            ],
        ]);

        return [
            'asset' => $asset,
            'upload_url' => $upload['url'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateMetadata(MediaAsset $asset, array $attributes): MediaAsset
    {
        $asset->fill([
            'title' => $attributes['title'],
            'alt_text' => $attributes['alt_text'] ?? null,
        ])->save();

        PublicCmsContentService::bumpCacheVersion();

        return $asset->fresh() ?? $asset;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function replaceUpload(User $actor, MediaAsset $asset, UploadedFile $file, array $attributes): MediaAsset
    {
        if ($asset->type === MediaAssetType::ShortVideo) {
            throw new MediaUploadException('Mux videos must be replaced with a new direct upload.');
        }

        $replacement = $this->storeUploads($actor, [
            'type' => $asset->type->value,
            'title' => $attributes['title'] ?? $asset->title,
            'alt_text' => $attributes['alt_text'] ?? $asset->alt_text,
            'is_public' => (bool) ($attributes['is_public'] ?? $asset->is_public),
            'duration_seconds' => $asset->duration_seconds,
            'metadata' => $asset->metadata ?? [],
        ], [$file])->first();

        if (! $replacement instanceof MediaAsset) {
            throw new MediaUploadException('Replacement upload did not create a media asset.');
        }

        $oldDisk = $asset->disk;
        $oldPath = $asset->path;

        try {
            DB::transaction(function () use ($actor, $asset, $replacement): void {
                $asset->forceFill([
                    'title' => $replacement->title,
                    'disk' => $replacement->disk,
                    'path' => $replacement->path,
                    'original_filename' => $replacement->original_filename,
                    'mime_type' => $replacement->mime_type,
                    'extension' => $replacement->extension,
                    'size_bytes' => $replacement->size_bytes,
                    'checksum' => $replacement->checksum,
                    'is_public' => $replacement->is_public,
                    'alt_text' => $replacement->alt_text,
                    'width' => $replacement->width,
                    'height' => $replacement->height,
                    'processing_status' => $replacement->processing_status,
                    'uploaded_by_id' => $actor->id,
                    'metadata' => [
                        ...($replacement->metadata ?? []),
                        'replaced_at' => now()->toISOString(),
                    ],
                ])->save();

                $replacement->delete();
            });
        } catch (Throwable $exception) {
            Storage::disk($replacement->disk)->delete($replacement->path);
            MediaAsset::query()->whereKey($replacement->id)->delete();
            report($exception);

            throw new MediaUploadException('Replacement failed before references could be preserved.', previous: $exception);
        }

        if ($oldDisk && $oldPath && $oldDisk !== 'mux') {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        PublicCmsContentService::bumpCacheVersion();

        return $asset->fresh() ?? $asset;
    }

    public function delete(MediaAsset $asset): void
    {
        if ($this->isReferenced($asset)) {
            throw ValidationException::withMessages([
                'asset' => 'This media asset is still referenced. Replace or detach it before deleting.',
            ]);
        }

        $disk = $asset->disk;
        $path = $asset->path;

        $asset->delete();

        if ($disk && $path && $disk !== 'mux') {
            Storage::disk($disk)->delete($path);
        }

        PublicCmsContentService::bumpCacheVersion();
    }

    public function applyMuxWebhook(array $event): ?MediaAsset
    {
        $eventType = (string) Arr::get($event, 'type');
        $data = Arr::get($event, 'data', []);

        if (! is_array($data)) {
            return null;
        }

        $asset = $this->resolveMuxAsset($data);

        if (! $asset) {
            return null;
        }

        $metadata = [
            ...($asset->metadata ?? []),
            'mux_last_event_type' => $eventType,
            'mux_last_event_id' => Arr::get($event, 'id'),
        ];

        match ($eventType) {
            'video.upload.asset_created' => $asset->forceFill([
                'mux_asset_id' => Arr::get($data, 'asset_id', $asset->mux_asset_id),
                'processing_status' => MediaProcessingStatus::Processing->value,
                'mux_status' => 'asset_created',
                'metadata' => $metadata,
            ])->save(),
            'video.asset.created', 'video.asset.processing' => $asset->forceFill([
                'mux_asset_id' => Arr::get($data, 'id', $asset->mux_asset_id),
                'processing_status' => MediaProcessingStatus::Processing->value,
                'mux_status' => Arr::get($data, 'status', 'processing'),
                'metadata' => $metadata,
            ])->save(),
            'video.asset.ready' => $asset->forceFill([
                'mux_asset_id' => Arr::get($data, 'id', $asset->mux_asset_id),
                'mux_playback_id' => Arr::get($data, 'playback_ids.0.id', $asset->mux_playback_id),
                'processing_status' => MediaProcessingStatus::Ready->value,
                'mux_status' => Arr::get($data, 'status', 'ready'),
                'metadata' => $metadata,
            ])->save(),
            'video.asset.errored', 'video.upload.errored', 'video.upload.cancelled' => $asset->forceFill([
                'processing_status' => MediaProcessingStatus::Failed->value,
                'mux_status' => Arr::get($data, 'status', 'errored'),
                'mux_error' => $this->muxErrorMessage($data),
                'metadata' => $metadata,
            ])->save(),
            default => $asset->forceFill([
                'metadata' => $metadata,
            ])->save(),
        };

        return $asset->fresh();
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $storedPaths
     */
    private function deleteStoredPaths(array $storedPaths): void
    {
        foreach ($storedPaths as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function resolveMuxAsset(array $data): ?MediaAsset
    {
        $passthrough = Arr::get($data, 'passthrough');

        if (is_string($passthrough) && $passthrough !== '') {
            $asset = MediaAsset::query()->where('uuid', $passthrough)->first();

            if ($asset) {
                return $asset;
            }
        }

        $uploadId = Arr::get($data, 'id');

        if (is_string($uploadId)) {
            $asset = MediaAsset::query()->where('mux_upload_id', $uploadId)->first();

            if ($asset) {
                return $asset;
            }
        }

        $assetId = Arr::get($data, 'asset_id') ?: Arr::get($data, 'id');

        return is_string($assetId)
            ? MediaAsset::query()->where('mux_asset_id', $assetId)->first()
            : null;
    }

    private function muxErrorMessage(array $data): ?string
    {
        return Arr::get($data, 'errors.messages.0')
            ?: Arr::get($data, 'error.message')
            ?: Arr::get($data, 'error')
            ?: 'Mux reported a processing error.';
    }

    private function isReferenced(MediaAsset $asset): bool
    {
        if ($asset->editorialContents()->exists()) {
            return true;
        }

        if (SitePageSetting::query()->where('media_asset_id', $asset->id)->exists()) {
            return true;
        }

        $referencedInSettings = SitePageSetting::query()
            ->select(['id', 'payload'])
            ->get()
            ->contains(fn (SitePageSetting $setting): bool => $this->payloadReferencesAsset($setting->payload, $asset->id));

        if ($referencedInSettings) {
            return true;
        }

        return EditorialContent::query()
            ->select(['id', 'metadata'])
            ->get()
            ->contains(fn (EditorialContent $content): bool => $this->payloadReferencesAsset($content->metadata, $asset->id));
    }

    private function payloadReferencesAsset(mixed $payload, int $assetId, ?string $key = null): bool
    {
        if (! is_array($payload)) {
            return $key !== null
                && (str_ends_with($key, '_asset_id') || str_ends_with($key, '_asset_ids'))
                && is_numeric($payload)
                && (int) $payload === $assetId;
        }

        foreach ($payload as $childKey => $value) {
            $referenceKey = is_int($childKey) ? $key : (string) $childKey;

            if ($this->payloadReferencesAsset($value, $assetId, $referenceKey)) {
                return true;
            }
        }

        return false;
    }
}
