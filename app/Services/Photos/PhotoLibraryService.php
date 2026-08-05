<?php

namespace App\Services\Photos;

use App\Enums\PhotoStatus;
use App\Enums\PhotoVisibility;
use App\Jobs\ProcessPhotoVariants;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\User;
use App\Services\PublicCmsContentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PhotoLibraryService
{
    public function __construct(private readonly PhotoVariantGenerator $variants) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $files
     * @return array{album: PhotoAlbum|null, photos: Collection<int, Photo>, queued: bool}
     */
    public function storeUploads(User $actor, array $attributes, array $files): array
    {
        $album = $this->albumForUpload($attributes);
        $queued = count($files) > (int) config('photos.large_batch_threshold', 15);
        $photos = collect();
        $startOrder = $album
            ? ((int) $album->photos()->max('order_index')) + 1
            : ((int) Photo::query()->whereNull('album_id')->max('order_index')) + 1;

        foreach ($files as $index => $file) {
            $photo = $this->storeOriginal($actor, $album, $file, $attributes, $index, $startOrder + $index);
            $photos->push($photo);

            if ($queued) {
                dispatch(new ProcessPhotoVariants($photo->id));

                continue;
            }

            $this->process($photo);
        }

        if ($album && $album->cover_photo_id === null && $photos->first()) {
            $album->forceFill(['cover_photo_id' => $photos->first()->id])->save();
        }

        PublicCmsContentService::bumpCacheVersion();

        return [
            'album' => $album,
            'photos' => $photos,
            'queued' => $queued,
        ];
    }

    public function process(Photo $photo): Photo
    {
        $photo = $photo->fresh() ?? $photo;
        $oldPaths = $this->variantPaths($photo);
        $legacyAssetPath = $this->legacyAssetPath($photo);

        try {
            $photo = $this->preserveLegacyOriginal($photo);
            $generated = $this->variants->generate($photo);
            $metadata = $photo->metadata ?? [];
            unset($metadata['processing_error']);

            if ($legacyAssetPath) {
                unset($metadata['legacy_asset_path']);
                $metadata['legacy_asset_retired_path'] = $legacyAssetPath;
                $metadata['legacy_asset_retired_at'] = now()->toISOString();
            }

            $photo->forceFill([
                ...$generated,
                'status' => PhotoStatus::Active,
                'metadata' => [
                    ...$metadata,
                    'processed_at' => now()->toISOString(),
                ],
            ])->save();

            $this->deletePaths($oldPaths);
            $this->deletePublicLegacyAsset($legacyAssetPath);
            PublicCmsContentService::bumpCacheVersion();
        } catch (Throwable $exception) {
            report($exception);
            $metadata = $photo->metadata ?? [];

            if (
                $legacyAssetPath
                && $photo->visibility === PhotoVisibility::MemberOnly
                && $photo->original_disk
                && $photo->original_path
            ) {
                unset($metadata['legacy_asset_path']);
                $metadata['legacy_asset_retired_path'] = $legacyAssetPath;
                $metadata['legacy_asset_retired_at'] = now()->toISOString();
                $this->deletePublicLegacyAsset($legacyAssetPath);
            }

            $photo->forceFill([
                'status' => PhotoStatus::Processing,
                'metadata' => [
                    ...$metadata,
                    'processing_error' => $exception->getMessage(),
                ],
            ])->save();
        }

        return $photo->fresh() ?? $photo;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Photo $photo, array $attributes): Photo
    {
        $oldVisibility = $photo->visibility;

        $photo->fill([
            'album_id' => $attributes['album_id'] ?? null,
            'visibility' => PhotoVisibility::from($attributes['visibility']),
            'caption' => $attributes['caption'] ?? null,
            'order_index' => (int) ($attributes['order_index'] ?? $photo->order_index),
            'status' => PhotoStatus::from($attributes['status'] ?? $photo->status->value),
        ])->save();

        if ($oldVisibility !== $photo->visibility && $this->hasProcessableSource($photo) && $photo->status === PhotoStatus::Active) {
            $this->process($photo);
        }

        PublicCmsContentService::bumpCacheVersion();

        return $photo->fresh() ?? $photo;
    }

    /**
     * @param  iterable<int, Photo>  $photos
     */
    public function setVisibility(iterable $photos, PhotoVisibility $visibility): void
    {
        foreach ($photos as $photo) {
            $this->update($photo, [
                'album_id' => $photo->album_id,
                'visibility' => $visibility->value,
                'caption' => $photo->caption,
                'order_index' => $photo->order_index,
                'status' => $photo->status->value,
            ]);
        }
    }

    public function delete(Photo $photo): void
    {
        $paths = [
            ...$this->variantPaths($photo),
            [$photo->original_disk, $photo->original_path],
        ];
        $legacyAssetPath = $this->legacyAssetPath($photo);
        $album = $photo->album;

        $photo->delete();
        $this->deletePaths($paths);
        $this->deletePublicLegacyAsset($legacyAssetPath);

        if ($album && (int) $album->cover_photo_id === (int) $photo->id) {
            $album->forceFill([
                'cover_photo_id' => $album->photos()->value('id'),
            ])->save();
        }

        PublicCmsContentService::bumpCacheVersion();
    }

    /**
     * @param  array<int|string, int|string>  $order
     */
    public function reorder(array $order): void
    {
        foreach ($order as $photoId => $index) {
            Photo::query()->whereKey((int) $photoId)->update(['order_index' => (int) $index]);
        }

        PublicCmsContentService::bumpCacheVersion();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function albumForUpload(array $attributes): ?PhotoAlbum
    {
        $albumId = (int) ($attributes['album_id'] ?? 0);

        if ($albumId === 0) {
            return null;
        }

        return PhotoAlbum::query()->findOrFail($albumId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storeOriginal(
        User $actor,
        ?PhotoAlbum $album,
        UploadedFile $file,
        array $attributes,
        int $index,
        int $orderIndex,
    ): Photo {
        $disk = config('photos.private_disk', 'local');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $filename = (string) Str::uuid().'.'.$extension;
        $path = Storage::disk($disk)->putFileAs('photos/originals/'.now()->format('Y/m'), $file, $filename);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Photo original could not be stored.');
        }

        return Photo::create([
            'album_id' => $album?->id,
            'original_disk' => $disk,
            'original_path' => $path,
            'visibility' => PhotoVisibility::from($attributes['visibility'][$index] ?? PhotoVisibility::Public->value),
            'status' => PhotoStatus::Processing,
            'order_index' => $orderIndex,
            'caption' => $attributes['captions'][$index] ?? null,
            'uploaded_by_id' => $actor->id,
            'metadata' => [
                'source' => 'cms_upload',
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
            ],
        ]);
    }

    private function hasProcessableSource(Photo $photo): bool
    {
        if ($photo->original_path) {
            return true;
        }

        $legacy = $this->legacyAssetPath($photo);

        return is_string($legacy) && $legacy !== '' && is_file(public_path($legacy));
    }

    private function preserveLegacyOriginal(Photo $photo): Photo
    {
        if ($photo->original_disk && $photo->original_path) {
            return $photo;
        }

        $legacy = $this->legacyAssetPath($photo);

        if (! $legacy) {
            return $photo;
        }

        $sourcePath = public_path($legacy);

        if (! is_file($sourcePath)) {
            return $photo;
        }

        $contents = file_get_contents($sourcePath);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Legacy photo original could not be read.');
        }

        $disk = config('photos.private_disk', 'local');
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg');
        $path = 'photos/originals/legacy/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;

        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException('Legacy photo original could not be stored.');
        }

        $photo->forceFill([
            'original_disk' => $disk,
            'original_path' => $path,
            'metadata' => [
                ...($photo->metadata ?? []),
                'legacy_preserved_from' => $legacy,
                'legacy_preserved_at' => now()->toISOString(),
            ],
        ])->save();

        return $photo->fresh() ?? $photo;
    }

    private function legacyAssetPath(Photo $photo): ?string
    {
        $legacy = data_get($photo->metadata ?? [], 'legacy_asset_path');

        if (! is_string($legacy) || $legacy === '') {
            return null;
        }

        $legacy = str_replace('\\', '/', $legacy);

        return str_starts_with($legacy, 'images/photos/') && ! str_contains($legacy, '..') ? $legacy : null;
    }

    private function deletePublicLegacyAsset(?string $path): void
    {
        if (! $path) {
            return;
        }

        $publicPath = public_path($path);

        if (is_file($publicPath)) {
            unlink($publicPath);
        }
    }

    /**
     * @return array<int, array{0: string|null, 1: string|null}>
     */
    private function variantPaths(Photo $photo): array
    {
        return [
            [$photo->public_disk, $photo->public_path],
            [$photo->blurred_disk, $photo->blurred_path],
            [$photo->thumbnail_disk, $photo->thumbnail_path],
        ];
    }

    /**
     * @param  array<int, array{0: string|null, 1: string|null}>  $paths
     */
    private function deletePaths(array $paths): void
    {
        foreach ($paths as [$disk, $path]) {
            if ($disk && $path) {
                Storage::disk($disk)->delete($path);
            }
        }
    }
}
