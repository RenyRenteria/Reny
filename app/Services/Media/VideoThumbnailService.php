<?php

namespace App\Services\Media;

use App\Enums\MediaAssetType;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class VideoThumbnailService
{
    public function __construct(
        private readonly VideoThumbnailGenerator $generator,
        private readonly MediaLibraryService $library,
    ) {}

    public function createFor(User $actor, MediaAsset $video, ?string $title = null): MediaAsset
    {
        $temporaryPath = $this->generator->generate($video);
        $baseName = Str::slug(pathinfo($video->original_filename, PATHINFO_FILENAME)) ?: 'video';

        try {
            $upload = new UploadedFile(
                $temporaryPath,
                $baseName.'-thumbnail.jpg',
                'image/jpeg',
                null,
                true,
            );
            $thumbnail = $this->library->storeUploads($actor, [
                'type' => MediaAssetType::Thumbnail->value,
                'title' => trim((string) ($title ?: $video->title ?: $baseName)).' video thumbnail',
                'is_public' => true,
                'alt_text' => trim((string) ($title ?: $video->title ?: $baseName)),
                'metadata' => [
                    'source' => 'community_video_thumbnail',
                    'video_asset_id' => $video->id,
                ],
            ], [$upload])->first();

            if (! $thumbnail instanceof MediaAsset) {
                throw new MediaUploadException('No se pudo guardar la miniatura del video.');
            }

            return $thumbnail;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
