<?php

namespace App\Services\PublicCms;

use App\Models\EditorialContent;
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

        foreach ($contents as $content) {
            $payload = $this->video($content);
            $groups[$payload['group']][] = $payload;
        }

        return [
            ...$groups,
            'featured_video' => $this->featured($contents->first()),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function featured(?EditorialContent $content): ?array
    {
        if (! $content instanceof EditorialContent) {
            return null;
        }

        $youtubeId = $this->media->youtubeId((string) $this->media->metadata($content, 'youtube_url', ''));

        if ($youtubeId === null) {
            return null;
        }

        return [
            'id' => $youtubeId,
            'title' => $content->title,
            'meta' => $content->summary ?: 'Featured CMS video',
            'url' => route('public.content.show', $content),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function video(EditorialContent $content): array
    {
        $youtubeId = $this->media->youtubeId((string) $this->media->metadata($content, 'youtube_url', ''));
        $category = str((string) $this->media->metadata($content, 'category', 'music-video'))->lower()->slug('_')->toString();
        $group = match ($category) {
            'playlist', 'series', 'series_playlist', 'music_playlist' => 'series',
            'performance', 'performances', 'live', 'live_performance' => 'performances',
            'behind_the_scenes', 'behind', 'bts', 'studio' => 'behind_the_scenes',
            'vlog', 'vlogs' => 'vlogs',
            default => 'music_videos',
        };

        return [
            'id' => $youtubeId,
            'title' => e($content->title),
            'meta' => e($content->summary ?: (string) $this->media->metadata($content, 'playlist', 'CMS video')),
            'external_url' => $youtubeId ? "https://www.youtube.com/watch?v={$youtubeId}" : null,
            'group' => $group,
            'play_state' => $youtubeId ? 'ready' : 'unavailable',
            'url' => route('public.content.show', $content),
        ];
    }
}
