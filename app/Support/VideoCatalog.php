<?php

namespace App\Support;

use App\Models\EditorialContent;

final class VideoCatalog
{
    /**
     * @return array<string, array{label: string, kind: string, color: string}>
     */
    public static function groups(): array
    {
        return [
            'music_videos' => ['label' => 'Music Videos', 'kind' => 'Videos', 'color' => '#a12867'],
            'series' => ['label' => 'Series (Playlists)', 'kind' => 'Playlists', 'color' => '#7251c9'],
            'performances' => ['label' => 'Performances', 'kind' => 'Videos', 'color' => '#d76c31'],
            'behind_the_scenes' => ['label' => 'Behind the Scenes', 'kind' => 'Videos', 'color' => '#2b779b'],
            'vlogs' => ['label' => 'Vlogs', 'kind' => 'Videos', 'color' => '#28856b'],
        ];
    }

    public static function groupFor(EditorialContent|array|string|null $source): string
    {
        $category = match (true) {
            $source instanceof EditorialContent => data_get($source->metadata, 'category'),
            is_array($source) => data_get($source, 'category'),
            default => $source,
        };
        $category = str((string) $category)->lower()->slug('_')->toString();

        return match ($category) {
            'playlist', 'series', 'series_playlist', 'music_playlist' => 'series',
            'performance', 'performances', 'live', 'live_performance' => 'performances',
            'behind_the_scenes', 'behind', 'bts', 'studio' => 'behind_the_scenes',
            'vlog', 'vlogs' => 'vlogs',
            default => 'music_videos',
        };
    }

    public static function sortOrder(EditorialContent $content): int
    {
        $value = data_get($content->metadata, 'sort_order');

        return is_numeric($value) ? max(0, (int) $value) : PHP_INT_MAX;
    }

    public static function isFeatured(EditorialContent $content): bool
    {
        return filter_var(data_get($content->metadata, 'is_featured', false), FILTER_VALIDATE_BOOL);
    }

    public static function isFeaturedOnly(EditorialContent $content): bool
    {
        return filter_var(data_get($content->metadata, 'featured_only', false), FILTER_VALIDATE_BOOL);
    }
}
