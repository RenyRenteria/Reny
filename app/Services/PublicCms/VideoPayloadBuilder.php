<?php

namespace App\Services\PublicCms;

use App\Models\EditorialContent;
use App\Support\VideoCatalog;
use Illuminate\Support\Collection;

class VideoPayloadBuilder
{
    public function __construct(
        private readonly PayloadMediaResolver $media,
    ) {}

    /**
     * @param  Collection<int, EditorialContent>  $contents
     * @return array<string, mixed>
     */
    public function build(Collection $contents): array
    {
        $groups = [
            'music_videos' => [],
            'series' => [],
            'performances' => [],
            'behind_the_scenes' => [],
            'vlogs' => [],
        ];

        $groupOrder = array_flip(array_keys(VideoCatalog::groups()));
        $catalogContents = $contents
            ->reject(fn (EditorialContent $content): bool => VideoCatalog::isFeaturedOnly($content))
            ->sortBy(fn (EditorialContent $content): string => sprintf(
                '%02d-%010d-%010d',
                $groupOrder[VideoCatalog::groupFor($content)] ?? 99,
                VideoCatalog::sortOrder($content),
                $content->id,
            ));

        foreach ($catalogContents as $content) {
            $payload = $this->video($content);
            $groups[$payload['group']][] = $payload;
        }

        return [
            ...$groups,
            'featured_video' => $this->featuredFrom($contents),
        ];
    }

    /**
     * @param  Collection<int, EditorialContent>  $contents
     * @return array<string, mixed>|null
     */
    public function featuredFrom(Collection $contents): ?array
    {
        $featuredContent = $contents->first(
            fn (EditorialContent $content): bool => VideoCatalog::isFeatured($content)
                && $this->canFeature($content)
        ) ?? $contents->first(fn (EditorialContent $content): bool => $this->canFeature($content));

        return $this->featured($featuredContent);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function featured(?EditorialContent $content): ?array
    {
        if (! $content instanceof EditorialContent) {
            return null;
        }

        $youtubeUrl = (string) $this->media->metadata($content, 'youtube_url', '');
        $youtubeId = $this->media->youtubeId($youtubeUrl);

        if ($youtubeId === null) {
            return null;
        }

        return [
            'id' => $youtubeId,
            'title' => $content->title,
            'meta' => $content->summary ?: 'Featured CMS video',
            'external_url' => $youtubeUrl,
            'url' => route('public.content.show', $content),
        ];
    }

    private function canFeature(EditorialContent $content): bool
    {
        return VideoCatalog::groupFor($content) !== 'series'
            && $this->media->youtubeId(
                (string) $this->media->metadata($content, 'youtube_url', '')
            ) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function video(EditorialContent $content): array
    {
        $youtubeUrl = (string) $this->media->metadata($content, 'youtube_url', '');
        $youtubeId = $this->media->youtubeId($youtubeUrl);
        $group = VideoCatalog::groupFor($content);
        $hasYoutubeDestination = $youtubeId !== null
            || ($group === 'series' && $this->media->youtubePlaylistId($youtubeUrl) !== null);

        return [
            'id' => $youtubeId,
            'title' => e($content->title),
            'meta' => e($content->summary ?: (string) $this->media->metadata($content, 'playlist', 'CMS video')),
            'external_url' => $hasYoutubeDestination ? $youtubeUrl : null,
            'group' => $group,
            'play_state' => $hasYoutubeDestination ? 'ready' : 'unavailable',
            'url' => route('public.content.show', $content),
        ];
    }
}
