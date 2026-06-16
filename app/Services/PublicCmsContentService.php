<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PublicCmsContentService
{
    private const PAGES = ['music', 'videos', 'photos', 'store', 'community'];

    private const VERSION_KEY = 'public-cms:last-published:version';

    private const USER_VERSION_PREFIX = 'public-cms:last-published:user-version:';

    /**
     * @return array<string, mixed>
     */
    public function music(?User $user): array
    {
        return $this->page('music', $user, function () use ($user): array {
            $contents = $this->visibleContents($user, [
                ContentType::Song,
                ContentType::MusicalAlbum,
                ContentType::DeluxeAlbum,
                ContentType::Exclusive,
            ])->get();

            $albums = $contents
                ->whereIn('type', [ContentType::MusicalAlbum, ContentType::DeluxeAlbum])
                ->values()
                ->map(fn (EditorialContent $content, int $index): array => $this->albumPayload($content, $index))
                ->all();

            $singles = $contents
                ->whereIn('type', [ContentType::Song, ContentType::Exclusive])
                ->values()
                ->map(fn (EditorialContent $content): array => $this->singlePayload($content))
                ->all();

            return [
                'albums' => $albums,
                'singles' => $singles,
                'featured' => $this->featuredMusicPayload($contents->first()),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function videos(?User $user): array
    {
        return $this->page('videos', $user, function () use ($user): array {
            $contents = $this->visibleContents($user, [ContentType::Video])->get();
            $groups = [
                'music_videos' => [],
                'series' => [],
                'performances' => [],
                'behind_the_scenes' => [],
                'vlogs' => [],
            ];

            foreach ($contents as $content) {
                $payload = $this->videoPayload($content);
                $groups[$payload['group']][] = $payload;
            }

            return [
                ...$groups,
                'featured_video' => $this->featuredVideoPayload($contents->first()),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function photos(?User $user): array
    {
        return $this->page('photos', $user, function () use ($user): array {
            return [
                'photos' => $this->visibleContents($user, [ContentType::Photo, ContentType::Gallery])
                    ->get()
                    ->values()
                    ->map(fn (EditorialContent $content, int $index): array => $this->photoPayload($content, $index))
                    ->all(),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function store(?User $user): array
    {
        return $this->page('store', $user, function () use ($user): array {
            $contents = $this->visibleContents($user, [
                ContentType::Product,
                ContentType::Event,
                ContentType::Drop,
                ContentType::Exclusive,
            ])->get();

            return [
                'products' => $contents
                    ->whereIn('type', [ContentType::Product, ContentType::Drop, ContentType::Exclusive])
                    ->values()
                    ->map(fn (EditorialContent $content): array => $this->productPayload($content))
                    ->all(),
                'events' => $contents
                    ->where('type', ContentType::Event)
                    ->values()
                    ->map(fn (EditorialContent $content): array => $this->eventPayload($content))
                    ->all(),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function community(?User $user): array
    {
        return $this->page('community', $user, function () use ($user): array {
            $contents = $this->visibleContents($user, [ContentType::Post, ContentType::Poll])->get();
            $poll = $contents->firstWhere('type', ContentType::Poll);

            return [
                'posts' => $contents
                    ->where('type', ContentType::Post)
                    ->values()
                    ->map(fn (EditorialContent $content): array => $this->postPayload($content))
                    ->all(),
                'poll' => $poll instanceof EditorialContent ? $this->pollPayload($poll) : null,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(string $page, ?User $user): array
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        return match ($page) {
            'music' => $this->music($user),
            'videos' => $this->videos($user),
            'photos' => $this->photos($user),
            'store' => $this->store($user),
            'community' => $this->community($user),
        };
    }

    public static function forgetCachedUserPayloads(User $user): void
    {
        $cache = self::cacheStore();
        $version = self::userCacheVersion($cache, $user);

        $cache->forever(self::userVersionKey($user), $version + 1);
    }

    public static function bumpCacheVersion(): bool
    {
        $cache = self::cacheStore();
        $version = self::cacheVersion($cache);

        return $cache->forever(self::VERSION_KEY, $version + 1);
    }

    /**
     * @param  array<int, ContentType>  $types
     */
    private function visibleContents(?User $user, array $types): Builder
    {
        return EditorialContent::query()
            ->with([
                'mediaAssets' => fn ($query) => $query->orderBy('content_media_assets.sort_order'),
                'releaseWindows',
            ])
            ->whereIn('type', array_map(fn (ContentType $type): string => $type->value, $types))
            ->visibleFor($user)
            ->orderByRaw('COALESCE(published_at, scheduled_at, created_at) DESC')
            ->limit(24);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $page, ?User $user, callable $loader): array
    {
        $cacheKey = $this->cacheKey($page, $user);

        try {
            $payload = $loader();

            $this->cache()->forever($cacheKey, $payload);

            return [
                ...$payload,
                '_cms_source' => 'cms',
                '_cms_fallback' => false,
            ];
        } catch (Throwable $exception) {
            report($exception);

            $cached = $this->cache()->get($cacheKey);

            if (is_array($cached)) {
                return [
                    ...$cached,
                    '_cms_source' => 'cache',
                    '_cms_fallback' => true,
                ];
            }
        }

        return [
            '_cms_source' => 'static',
            '_cms_fallback' => true,
        ];
    }

    private function cache(): Repository
    {
        return self::cacheStore();
    }

    private function cacheKey(string $page, ?User $user): string
    {
        $version = self::cacheVersion($this->cache());

        if ($user === null) {
            return "public-cms:last-published:v{$version}:{$page}:guest";
        }

        $viewer = $user->fresh() ?? $user;
        $userVersion = self::userCacheVersion($this->cache(), $viewer);
        $access = $this->accessFingerprint($viewer);

        return "public-cms:last-published:v{$version}:{$page}:user:{$viewer->id}:uv{$userVersion}:access:{$access}";
    }

    private static function cacheStore(): Repository
    {
        return Cache::store(config('public_cms.cache_store', config('cache.default')));
    }

    private static function cacheVersion(Repository $cache): int
    {
        return (int) $cache->get(self::VERSION_KEY, 1);
    }

    private static function userCacheVersion(Repository $cache, User $user): int
    {
        return (int) $cache->get(self::userVersionKey($user), 1);
    }

    private static function userVersionKey(User $user): string
    {
        return self::USER_VERSION_PREFIX.$user->id;
    }

    private function accessFingerprint(User $user): string
    {
        $availableUnlocks = $user->unlocks()
            ->available()
            ->orderBy('id')
            ->get(['id', 'product_key', 'source_type', 'source_id', 'updated_at'])
            ->map(fn ($unlock): string => implode(':', [
                $unlock->id,
                $unlock->product_key ?? '',
                $unlock->source_type ?? '',
                $unlock->source_id ?? '',
                $unlock->updated_at?->getTimestamp() ?? '',
            ]))
            ->implode('|');

        return sha1(implode('|', [
            'royal:'.($user->hasRoyalAccess() || $user->isStaff() ? '1' : '0'),
            'unlocks:'.$availableUnlocks,
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function featuredMusicPayload(?EditorialContent $content): ?array
    {
        if (! $content instanceof EditorialContent) {
            return null;
        }

        return [
            'eyebrow' => $this->metadata($content, 'eyebrow', 'CMS Release'),
            'title' => $content->title,
            'subtitle' => $content->summary ?: $this->metadata($content, 'subtitle', 'Latest published drop'),
            'copy' => $content->body ?: $content->summary,
            'url' => route('public.content.show', $content),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function albumPayload(EditorialContent $content, int $index): array
    {
        return [
            'title' => $content->title,
            'meta' => $this->metadata($content, 'track_count')
                ? $this->metadata($content, 'track_count').' tracks'
                : $this->lineCount((string) $this->metadata($content, 'tracklist')).' tracks',
            'cover_class' => ['cover-a', 'cover-b', 'cover-c', 'cover-d'][$index % 4],
            'image_url' => $this->mediaUrl($content, ['cover_asset_id', 'image_asset_id']),
            'url' => route('public.content.show', $content),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function singlePayload(EditorialContent $content): array
    {
        return [
            'title' => $content->title,
            'artist' => $this->metadata($content, 'artist', 'Reny Renteria'),
            'url' => route('public.content.show', $content),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function featuredVideoPayload(?EditorialContent $content): ?array
    {
        if (! $content instanceof EditorialContent) {
            return null;
        }

        $youtubeId = $this->youtubeId((string) $this->metadata($content, 'youtube_url', ''));

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
    private function videoPayload(EditorialContent $content): array
    {
        $youtubeId = $this->youtubeId((string) $this->metadata($content, 'youtube_url', '')) ?? 'Ue8orNrHw9s';
        $category = str((string) $this->metadata($content, 'category', 'music-video'))->lower()->slug('_')->toString();
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
            'meta' => e($content->summary ?: (string) $this->metadata($content, 'playlist', 'CMS video')),
            'group' => $group,
            'url' => route('public.content.show', $content),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photoPayload(EditorialContent $content, int $index): array
    {
        return [
            'image' => $this->metadata($content, 'fallback_image', 'cover.jpg'),
            'image_url' => $this->mediaUrl($content, ['image_asset_id']) ?? $this->metadata($content, 'image_url'),
            'type' => $content->type === ContentType::Gallery ? 'Album' : 'Single post',
            'tone' => $this->metadata($content, 'location', 'cms'),
            'title' => $content->title,
            'caption' => $this->metadata($content, 'caption', $content->summary ?? ''),
            'size' => ['wide', 'tall', 'standard'][$index % 3],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(EditorialContent $content): array
    {
        $price = ((int) $this->metadata($content, 'price_cents', 0)) / 100;
        $kind = (string) $this->metadata($content, 'product_kind', $this->metadata($content, 'drop_kind', 'digital'));

        return [
            'key' => $content->purchase_key ?: $this->metadata($content, 'sku', $content->slug),
            'name' => $content->title,
            'type' => str($kind)->headline()->toString(),
            'category' => $kind === 'physical' ? 'physical merch' : $kind,
            'price' => $price,
            'suffix' => $kind === 'subscription' ? '/mo' : '',
            'availability' => $this->availability($content),
            'points' => '+0 pts',
            'pass' => $content->visibility->value === 'open' ? 'No Royal Pass required' : 'Access-gated',
            'access' => $content->purchase_key ? 'Unlocks in profile' : 'Public checkout',
            'image' => $this->metadata($content, 'fallback_image', 'cover.jpg'),
            'image_url' => $this->mediaUrl($content, ['image_asset_id', 'cover_asset_id']),
            'summary' => $content->summary ?: $content->body ?: '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(EditorialContent $content): array
    {
        $startsAt = $this->metadata($content, 'starts_at');
        $price = ((int) $this->metadata($content, 'price_cents', 0)) / 100;

        return [
            'key' => $content->purchase_key ?: $this->metadata($content, 'sku', $content->slug),
            'name' => $content->title,
            'kicker' => str((string) $this->metadata($content, 'event_kind', 'event'))->headline()->toString(),
            'date' => $startsAt ? date('M d, Y', strtotime((string) $startsAt)) : 'Date TBA',
            'place' => $this->metadata($content, 'location', 'Online'),
            'price' => $price,
            'image' => $this->metadata($content, 'fallback_image', 'reny-store-concert-poster.png'),
            'image_url' => $this->mediaUrl($content, ['image_asset_id', 'cover_asset_id']),
            'action' => $this->metadata($content, 'ticketing_mode') === 'rsvp' ? 'RSVP' : 'Buy ticket',
            'mode' => $this->metadata($content, 'ticketing_mode') === 'rsvp' ? 'rsvp' : 'buy',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postPayload(EditorialContent $content): array
    {
        return [
            'title' => $content->title,
            'time' => $content->published_at?->diffForHumans() ?? 'Published',
            'body' => $content->body ?: $content->summary ?: '',
            'image_url' => $this->mediaUrl($content, ['image_asset_id']),
            'cta' => 'View Reny note',
            'url' => route('public.content.show', $content),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pollPayload(EditorialContent $content): array
    {
        $options = collect(Arr::wrap($this->metadata($content, 'options')))
            ->filter()
            ->values()
            ->map(fn (string $option, int $index): array => [
                'label' => $option,
                'percent' => [42, 34, 24, 18, 12, 8, 6, 4][$index] ?? 10,
            ])
            ->all();

        return [
            'question' => $this->metadata($content, 'question', $content->title),
            'options' => $options,
            'votes' => $this->metadata($content, 'votes', 'CMS poll'),
        ];
    }

    private function mediaUrl(EditorialContent $content, array $metadataKeys): ?string
    {
        $assetId = collect($metadataKeys)
            ->map(fn (string $key): mixed => $this->metadata($content, $key))
            ->filter()
            ->first();

        $asset = $content->mediaAssets
            ->when($assetId, fn (Collection $assets): Collection => $assets->where('id', (int) $assetId))
            ->first();

        if (! $asset instanceof MediaAsset) {
            $asset = $content->mediaAssets->first();
        }

        return $asset?->publicUrl();
    }

    private function metadata(EditorialContent $content, string $key, mixed $default = null): mixed
    {
        return data_get($content->metadata ?? [], $key, $default);
    }

    private function lineCount(string $value): int
    {
        $count = collect(preg_split('/\R/', $value) ?: [])
            ->filter(fn (string $line): bool => trim($line) !== '')
            ->count();

        return max(1, $count);
    }

    private function youtubeId(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (($parts['host'] ?? '') === 'youtu.be') {
            return trim($parts['path'] ?? '', '/');
        }

        parse_str($parts['query'] ?? '', $query);

        return isset($query['v']) ? (string) $query['v'] : null;
    }

    private function availability(EditorialContent $content): string
    {
        $inventory = $this->metadata($content, 'inventory');

        if (is_numeric($inventory)) {
            return ((int) $inventory).' available';
        }

        return 'Available now';
    }
}
