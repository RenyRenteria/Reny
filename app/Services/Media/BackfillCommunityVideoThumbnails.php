<?php

namespace App\Services\Media;

use App\Enums\ContentType;
use App\Enums\MediaAssetType;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\PublicCmsContentService;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillCommunityVideoThumbnails
{
    public function __construct(
        private readonly VideoThumbnailService $thumbnails,
        private readonly MediaLibraryService $library,
    ) {}

    /**
     * @return array{generated:int,skipped:int,failed:int,errors:array<int,string>}
     */
    public function handle(): array
    {
        $result = [
            'generated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        EditorialContent::query()
            ->where('type', ContentType::Post->value)
            ->with(['createdBy', 'mediaAssets.uploadedBy'])
            ->each(function (EditorialContent $content) use (&$result): void {
                $videos = $content->mediaAssets->filter(
                    fn (MediaAsset $asset): bool => $asset->type === MediaAssetType::Video
                        && $asset->pivot?->role === 'attachment'
                );

                foreach ($videos as $video) {
                    $thumbnail = $this->existingThumbnail($content, $video);

                    if ($thumbnail) {
                        $this->linkThumbnail($content, $video, $thumbnail);
                        $result['skipped']++;

                        continue;
                    }

                    $actor = $video->uploadedBy ?? $content->createdBy;

                    if (! $actor instanceof User) {
                        $result['failed']++;
                        $result['errors'][] = "Video {$video->id}: no tiene un usuario responsable para crear la miniatura.";

                        continue;
                    }

                    $createdThumbnail = null;

                    try {
                        $createdThumbnail = $this->thumbnails->createFor($actor, $video, $content->title);
                        $this->linkThumbnail($content, $video, $createdThumbnail);
                        $result['generated']++;
                    } catch (Throwable $exception) {
                        if ($createdThumbnail instanceof MediaAsset) {
                            $this->library->delete($createdThumbnail);
                        }

                        report($exception);
                        $result['failed']++;
                        $result['errors'][] = "Video {$video->id}: {$exception->getMessage()}";
                    }
                }
            });

        if ($result['generated'] > 0) {
            PublicCmsContentService::bumpCacheVersion();
        }

        return $result;
    }

    private function existingThumbnail(EditorialContent $content, MediaAsset $video): ?MediaAsset
    {
        $videoMetadata = $this->pivotMetadata($video->pivot?->metadata);
        $thumbnail = $content->mediaAssets->firstWhere(
            'id',
            (int) ($videoMetadata['thumbnail_asset_id'] ?? 0),
        );

        if ($thumbnail?->type === MediaAssetType::Thumbnail) {
            return $thumbnail;
        }

        return $content->mediaAssets->first(function (MediaAsset $asset) use ($video): bool {
            if ($asset->type !== MediaAssetType::Thumbnail || $asset->pivot?->role !== 'video_thumbnail') {
                return false;
            }

            $metadata = $this->pivotMetadata($asset->pivot?->metadata);

            return (int) ($metadata['video_asset_id'] ?? 0) === $video->id;
        });
    }

    private function linkThumbnail(
        EditorialContent $content,
        MediaAsset $video,
        MediaAsset $thumbnail,
    ): void {
        $videoMetadata = [
            ...$this->pivotMetadata($video->pivot?->metadata),
            'thumbnail_asset_id' => $thumbnail->id,
        ];

        DB::transaction(function () use ($content, $video, $thumbnail, $videoMetadata): void {
            $content->mediaAssets()->updateExistingPivot($video->id, [
                'metadata' => json_encode($videoMetadata),
            ]);
            $content->mediaAssets()->syncWithoutDetaching([
                $thumbnail->id => [
                    'role' => 'video_thumbnail',
                    'sort_order' => 100 + $video->id,
                    'metadata' => json_encode(['video_asset_id' => $video->id]),
                ],
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function pivotMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (! is_string($metadata) || $metadata === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }
}
