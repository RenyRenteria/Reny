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
        $sortOrder = $content instanceof EditorialContent
            ? data_get($content->metadata, 'sort_order')
            : ($metadata['sort_order'] ?? null);
        $metadata = [
            ...($content?->metadata ?? []),
            ...$metadata,
        ];
        $metadata['category'] = VideoCatalog::groupFor($metadata);
        $metadata['sort_order'] = is_numeric($sortOrder)
            ? max(0, (int) $sortOrder)
            : 999;
        $metadata['is_featured'] = VideoCatalog::groupFor($metadata) !== 'series'
            && filter_var($metadata['is_featured'] ?? false, FILTER_VALIDATE_BOOL);

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
            && VideoCatalog::groupFor($content) !== 'series'
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
                ->where('status', '!=', EditorialStatus::Archived->value)
                ->lockForUpdate()
                ->get()
                ->reject(fn (EditorialContent $content): bool => VideoCatalog::isFeaturedOnly($content))
                ->keyBy('id');

            $expectedIds = $videos->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $submittedIds = collect($videoIds)->sort()->values()->all();

            abort_unless(
                $expectedIds === $submittedIds,
                422,
                'The video order must contain the complete catalog.',
            );

            foreach (array_keys(VideoCatalog::groups()) as $group) {
                $groupVideoIds = collect($videoIds)
                    ->filter(fn (int $videoId): bool => VideoCatalog::groupFor($videos->get($videoId)) === $group)
                    ->values();

                foreach ($groupVideoIds as $position => $videoId) {
                    $video = $videos->get($videoId);
                    $metadata = $video->metadata ?? [];
                    $metadata['sort_order'] = $position + 1;
                    $video->forceFill([
                        'metadata' => $metadata,
                        'updated_by_id' => $actor->id,
                    ])->saveQuietly();
                }
            }
        });

        PublicCmsContentService::bumpCacheVersion();
    }
}
