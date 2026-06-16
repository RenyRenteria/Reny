<?php

namespace App\Services\Media;

use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                $isPublic = (bool) ($attributes['is_public'] ?? true);
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
        $upload = $mux->createDirectUpload($uuid);

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
            'is_public' => (bool) ($attributes['is_public'] ?? true),
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
}
