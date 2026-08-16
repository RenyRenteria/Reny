<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\SitePageSetting;
use App\Support\VideoCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicVideoCatalogSeeder
{
    /**
     * @return array{created: int, existing: int, page_settings_created: bool}
     */
    public function seed(): array
    {
        return DB::transaction(function (): array {
            $existingVideos = EditorialContent::query()
                ->where('type', ContentType::Video->value)
                ->get();
            $hasExistingFeatured = $existingVideos->contains(
                fn (EditorialContent $content): bool => VideoCatalog::isFeatured($content)
            );
            $created = 0;
            $existing = 0;

            foreach ($this->catalogRows(! $hasExistingFeatured) as $row) {
                $match = $existingVideos->first(function (EditorialContent $content) use ($row): bool {
                    $metadata = $content->metadata ?? [];

                    if (($metadata['public_catalog_key'] ?? null) === $row['metadata']['public_catalog_key']) {
                        return true;
                    }

                    return $content->title === $row['title']
                        && VideoCatalog::groupFor($content) === $row['metadata']['category']
                        && ($metadata['youtube_url'] ?? null) === $row['metadata']['youtube_url'];
                });

                if ($match) {
                    $existing++;

                    continue;
                }

                $content = EditorialContent::query()->create($row);
                $existingVideos->push($content);
                $created++;
            }

            $pageSettingsCreated = SitePageSetting::query()->firstOrCreate([
                'page' => 'videos',
                'section' => PageSettingsService::SECTION,
                'status' => SitePageSetting::STATUS_PUBLISHED,
            ], [
                'payload' => app(PageSettingsService::class)->defaults('videos'),
                'published_at' => now(),
            ])->wasRecentlyCreated;

            return compact('created', 'existing', 'pageSettingsCreated');
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalogRows(bool $featurePublicDefault): array
    {
        $featured = (array) config('reny_videos.featured', []);
        $rows = [[
            'type' => ContentType::Video->value,
            'title' => $featured['title'],
            'slug' => 'public-video-'.$featured['key'],
            'summary' => $featured['meta'],
            'status' => EditorialStatus::Published->value,
            'visibility' => VisibilityAudience::Open->value,
            'needs_approval' => false,
            'published_at' => now(),
            'metadata' => [
                'youtube_url' => $this->youtubeUrl($featured['youtube_id']),
                'category' => 'music_videos',
                'access_tier' => VisibilityAudience::Open->value,
                'sort_order' => 0,
                'is_featured' => $featurePublicDefault,
                'featured_only' => true,
                'public_catalog_key' => $featured['key'],
            ],
        ]];

        foreach ((array) config('reny_videos.groups', []) as $group => $videos) {
            foreach ($videos as $position => $video) {
                $rows[] = [
                    'type' => ContentType::Video->value,
                    'title' => html_entity_decode((string) $video['title']),
                    'slug' => Str::limit('public-video-'.$video['key'], 180, ''),
                    'summary' => html_entity_decode((string) $video['meta']),
                    'status' => EditorialStatus::Published->value,
                    'visibility' => VisibilityAudience::Open->value,
                    'needs_approval' => false,
                    'published_at' => now()->subSeconds($position + 1),
                    'metadata' => [
                        'youtube_url' => $this->youtubeUrl($video['youtube_id']),
                        'category' => $group,
                        'access_tier' => VisibilityAudience::Open->value,
                        'playlist' => $group === 'series' ? $video['title'] : null,
                        'sort_order' => $position + 1,
                        'is_featured' => false,
                        'public_catalog_key' => $video['key'],
                    ],
                ];
            }
        }

        return $rows;
    }

    private function youtubeUrl(string $youtubeId): string
    {
        return "https://www.youtube.com/watch?v={$youtubeId}";
    }
}
