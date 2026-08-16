<?php

namespace App\Services\Admin;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\PublicCms\PayloadMediaResolver;
use App\Services\PublicCmsContentService;
use App\Support\VideoCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VideoCatalogService
{
    public function __construct(private readonly PayloadMediaResolver $media) {}

    /**
     * @return Collection<int, EditorialContent>
     */
    public function contents(): Collection
    {
        $groupOrder = array_flip(array_keys(VideoCatalog::groups()));

        return EditorialContent::query()
            ->where('type', ContentType::Video->value)
            ->where('status', '!=', EditorialStatus::Archived->value)
            ->get()
            ->sortBy(fn (EditorialContent $content): string => sprintf(
                '%02d-%010d-%010d',
                $groupOrder[VideoCatalog::groupFor($content)] ?? 99,
                VideoCatalog::sortOrder($content),
                $content->id,
            ))
            ->values();
    }

    /**
     * Preserve metadata that is not exposed in the focused Videos editor.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function normalizeMetadata(array $metadata, ?EditorialContent $content = null): array
    {
        $metadata = [
            ...($content?->metadata ?? []),
            ...$metadata,
        ];
        $metadata['category'] = VideoCatalog::groupFor($metadata);
        $metadata['sort_order'] = is_numeric($metadata['sort_order'] ?? null)
            ? max(0, (int) $metadata['sort_order'])
            : 999;
        $metadata['is_featured'] = filter_var(
            $metadata['is_featured'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        return $metadata;
    }

    public function ensureSingleFeatured(EditorialContent $featured): void
    {
        if ($featured->type !== ContentType::Video || ! VideoCatalog::isFeatured($featured)) {
            return;
        }

        DB::transaction(function () use ($featured): void {
            EditorialContent::query()
                ->where('type', ContentType::Video->value)
                ->whereKeyNot($featured->getKey())
                ->lockForUpdate()
                ->get()
                ->filter(fn (EditorialContent $content): bool => VideoCatalog::isFeatured($content))
                ->each(function (EditorialContent $content): void {
                    $metadata = $content->metadata ?? [];
                    $metadata['is_featured'] = false;
                    $content->forceFill(['metadata' => $metadata])->saveQuietly();
                });
        });

        PublicCmsContentService::bumpCacheVersion();
    }

    public function setFeatured(User $actor, EditorialContent $featured): void
    {
        abort_unless($actor->canPublishContent(), 403);
        abort_unless($featured->type === ContentType::Video, 404);
        abort_unless($this->canFeature($featured), 422, 'Only a published YouTube video can be featured.');

        $metadata = $featured->metadata ?? [];
        $metadata['is_featured'] = true;
        $featured->forceFill([
            'metadata' => $metadata,
            'updated_by_id' => $actor->id,
        ])->save();

        $this->ensureSingleFeatured($featured);
    }

    public function canFeature(EditorialContent $content): bool
    {
        return $content->status === EditorialStatus::Published
            && $this->media->youtubeId((string) data_get($content->metadata, 'youtube_url')) !== null;
    }

    /**
     * @param  array<int, int>  $videoIds
     */
    public function reorder(User $actor, array $videoIds): void
    {
        abort_unless($actor->canPublishContent(), 403);

        DB::transaction(function () use ($actor, $videoIds): void {
            $videos = EditorialContent::query()
                ->where('type', ContentType::Video->value)
                ->whereIn('id', $videoIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            abort_unless($videos->count() === count($videoIds), 422, 'The video order contains an invalid item.');

            foreach ($videoIds as $position => $videoId) {
                $video = $videos->get($videoId);
                $metadata = $video->metadata ?? [];
                $metadata['sort_order'] = $position + 1;
                $video->forceFill([
                    'metadata' => $metadata,
                    'updated_by_id' => $actor->id,
                ])->saveQuietly();
            }
        });

        PublicCmsContentService::bumpCacheVersion();
    }
}
