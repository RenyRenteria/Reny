<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\MediaAssetType;
use App\Enums\VisibilityAudience;
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
    private const PAGES = ['home', 'music', 'videos', 'photos', 'store', 'community'];

    private const VERSION_KEY = 'public-cms:last-published:version';

    private const USER_VERSION_PREFIX = 'public-cms:last-published:user-version:';

    /**
     * @var array<int, ContentType>
     */
    private const MUSIC_ALBUM_TYPES = [
        ContentType::MusicalAlbum,
        ContentType::DeluxeAlbum,
    ];

    /**
     * @var array<int, ContentType>
     */
    private const MUSIC_SINGLE_TYPES = [
        ContentType::Song,
        ContentType::Exclusive,
    ];

    /**
     * @var array<int, ContentType>
     */
    private const MUSIC_PLAYLIST_TYPES = [
        ContentType::MusicPlaylist,
    ];

    public function __construct(
        private readonly MusicBannerSettingsService $musicBannerSettings,
        private readonly StorefrontSettingsService $storefrontSettings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function home(?User $user): array
    {
        return $this->page('home', $user, function () use ($user): array {
            $albums = $this->listableMusicContents('albums')
                ->limit(4)
                ->get()
                ->values()
                ->map(fn (EditorialContent $content, int $index): array => $this->albumPayload($content, $index, $user));

            $storefront = $this->storefrontSettings->publicPayload();
            $featuredVideo = $this->featuredVideoPayload(
                $this->visibleContents($user, [ContentType::Video])->first()
            );

            return [
                'featured_video' => $featuredVideo,
                'storefront' => $storefront,
                'events' => collect(['event_primary', 'event_secondary'])
                    ->map(fn (string $key): array => data_get($storefront, "slots.{$key}", []))
                    ->filter()
                    ->values()
                    ->all(),
                'album' => $this->homeAlbumPayload($albums, data_get($storefront, 'slots.album', [])),
                'singles' => $this->listableMusicContents('singles')
                    ->limit(3)
                    ->get()
                    ->values()
                    ->map(fn (EditorialContent $content): array => $this->singlePayload($content, $user))
                    ->all(),
                'royal_pass' => $storefront['royal_pass'] ?? [],
                'royal_visuals' => collect(['event_primary', 'album', 'event_secondary'])
                    ->map(fn (string $key): ?string => data_get($storefront, "slots.{$key}.image_url")
                        ?: (filled(data_get($storefront, "slots.{$key}.image"))
                            ? asset(data_get($storefront, "slots.{$key}.image"))
                            : null))
                    ->filter()
                    ->values()
                    ->all(),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function music(?User $user): array
    {
        return $this->page('music', $user, function () use ($user): array {
            $albums = $this->listableMusicContents('albums')
                ->limit(4)
                ->get()
                ->values()
                ->map(fn (EditorialContent $content, int $index): array => $this->albumPayload($content, $index, $user))
                ->all();

            $singles = $this->listableMusicContents('singles')
                ->limit(8)
                ->get()
                ->values()
                ->map(fn (EditorialContent $content): array => $this->singlePayload($content, $user))
                ->all();

            $playlists = $this->listableMusicContents('playlists')
                ->limit(6)
                ->get()
                ->values()
                ->map(fn (EditorialContent $content): array => $this->playlistPayload($content, $user))
                ->all();

            return [
                'banner' => $this->musicBannerSettings->publicPayload(),
                'albums' => $albums,
                'singles' => $singles,
                'playlists' => $playlists,
                'featured' => $this->featuredMusicPayload(
                    $this->listableMusicContents('all')->first()
                ),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function musicCollection(?User $user, string $section): array
    {
        abort_unless(in_array($section, ['albums', 'singles', 'playlists'], true), 404);

        return $this->page("music-{$section}", $user, function () use ($user, $section): array {
            $items = $this->listableMusicContents($section)
                ->limit(48)
                ->get()
                ->values()
                ->map(fn (EditorialContent $content, int $index): array => match ($section) {
                    'albums' => $this->albumPayload($content, $index, $user),
                    'playlists' => $this->playlistPayload($content, $user),
                    default => $this->singlePayload($content, $user),
                })
                ->all();

            return [
                'section' => $section,
                'items' => $items,
            ];
        });
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function musicPlayback(EditorialContent $content, ?User $user): array
    {
        $content->loadMissing([
            'mediaAssets' => fn ($query) => $query->orderBy('content_media_assets.sort_order'),
            'releaseWindows',
        ]);

        if (! $this->isMusicContent($content) || ! $this->isPubliclyListable($content)) {
            return [
                'status' => 404,
                'payload' => [
                    'state' => 'playback_error',
                    'message' => 'This music item is not available.',
                ],
            ];
        }

        $access = $this->musicAccessPayload($content, $user);

        if ($access['state'] !== 'ready') {
            return [
                'status' => $access['state'] === 'login_required' ? 401 : 403,
                'payload' => [
                    ...$access,
                    'title' => $content->title,
                    'detail_url' => route('public.content.show', $content),
                ],
            ];
        }

        $queue = $this->musicPlaybackQueue($content, $user);
        $audioUrl = $queue[0]['audio_url'] ?? $this->audioUrl($content);
        $basePayload = $this->musicBasePayload($content, $user);

        if ($audioUrl === null) {
            return [
                'status' => 422,
                'payload' => [
                    ...$basePayload,
                    'state' => 'playback_error',
                    'access_label' => 'Audio unavailable',
                    'message' => match (true) {
                        in_array($content->type, self::MUSIC_SINGLE_TYPES, true) => 'This single is published, but its audio source is not connected yet.',
                        in_array($content->type, self::MUSIC_PLAYLIST_TYPES, true) => 'This playlist is published, but none of its tracks can be played yet.',
                        default => 'This album is published, but a playable audio source is not connected yet.',
                    },
                    'cta_label' => 'Open details',
                    'cta_url' => route('public.content.show', $content),
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                ...$basePayload,
                'state' => 'ready',
                'access_label' => '',
                'message' => $queue[0]['title'] ?? $content->title,
                'audio_url' => $audioUrl,
                'queue' => $queue,
            ],
        ];
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
                'storefront' => $this->storefrontSettings->publicPayload(),
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
            'home' => $this->home($user),
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
     * @param  Collection<int, array<string, mixed>>  $albums
     * @param  array<string, mixed>  $storeAlbum
     * @return array<string, mixed>|null
     */
    private function homeAlbumPayload(Collection $albums, array $storeAlbum): ?array
    {
        $contentId = (string) ($storeAlbum['content_id'] ?? '');
        $album = $contentId !== ''
            ? $albums->firstWhere('id', $contentId)
            : $albums->first();

        if (! is_array($album)) {
            return $storeAlbum === []
                ? null
                : [
                    ...$storeAlbum,
                    'summary' => $storeAlbum['description'] ?? '',
                    'buy_label' => 'Buy Deluxe',
                ];
        }

        return [
            ...$album,
            'title' => $storeAlbum['title'] ?? $album['title'],
            'summary' => $storeAlbum['description'] ?? ($album['summary'] ?? ''),
            'image_url' => $storeAlbum['image_url'] ?? $album['image_url'] ?? null,
            'store_image' => $storeAlbum['image'] ?? null,
            'image_alt' => $storeAlbum['image_alt'] ?? $album['title'],
            'product_key' => $storeAlbum['product_key'] ?? 'deluxe',
            'buy_label' => 'Buy Deluxe',
        ];
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
    private function albumPayload(EditorialContent $content, int $index, ?User $user = null): array
    {
        return [
            ...$this->musicBasePayload($content, $user),
            'title' => $content->title,
            'meta' => count($this->tracklist($content)).' tracks',
            'cover_class' => ['cover-a', 'cover-b', 'cover-c', 'cover-d'][$index % 4],
            'image_url' => $this->mediaUrl($content, ['album_artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
            'kind' => 'album',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function singlePayload(EditorialContent $content, ?User $user = null): array
    {
        return [
            ...$this->musicBasePayload($content, $user),
            'title' => $content->title,
            'image_url' => $this->mediaUrl($content, ['artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
            'kind' => 'single',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function playlistPayload(EditorialContent $content, ?User $user = null): array
    {
        $tracks = $this->playlistTracks($content);

        return [
            'id' => (string) $content->getKey(),
            'visibility' => $content->visibility->value,
            'summary' => $content->summary ?: '',
            'detail_url' => route('public.content.show', $content),
            'play_url' => route('music.play', $content),
            'title' => $content->title,
            'meta' => count($tracks).' tracks',
            'tracks' => $tracks,
            'image_url' => $this->mediaUrl($content, ['playlist_cover_asset_id', 'image_asset_id']),
            'kind' => 'playlist',
            ...$this->musicAccessPayload($content, $user),
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
        $youtubeId = $this->youtubeId((string) $this->metadata($content, 'youtube_url', ''));
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
            'external_url' => $youtubeId ? "https://www.youtube.com/watch?v={$youtubeId}" : null,
            'group' => $group,
            'play_state' => $youtubeId ? 'ready' : 'unavailable',
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
        $isDrop = $content->type === ContentType::Drop;
        $category = match (true) {
            $kind === 'subscription' => 'membership',
            $isDrop, $kind === 'physical', $kind === 'merch' => 'merch',
            default => 'music',
        };

        return [
            'key' => $content->purchase_key ?: $this->metadata($content, 'sku', $content->slug),
            'name' => $content->title,
            'type' => $isDrop ? 'Art Drop' : str($kind)->headline()->toString(),
            'category' => $category,
            'price' => $price,
            'suffix' => $kind === 'subscription' ? '/mo' : '',
            'availability' => $this->availability($content),
            'points' => '+0 pts',
            'pass' => $content->visibility->value === 'open' ? 'No Royal Pass required' : 'Access-gated',
            'access' => $content->purchase_key ? 'Unlocks in profile' : 'Public checkout',
            'image' => $this->metadata($content, 'fallback_image', 'cover.jpg'),
            'image_url' => $this->mediaUrl($content, ['image_asset_id', 'cover_asset_id']),
            'summary' => $content->summary ?: $content->body ?: '',
            'cta' => match ($category) {
                'membership' => 'Join membership',
                'music' => 'Buy music',
                default => 'Add to bag',
            },
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
            'key' => 'cms-post-'.$content->id,
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
                'key' => 'option-'.($index + 1),
                'label' => $option,
                'percent' => [42, 34, 24, 18, 12, 8, 6, 4][$index] ?? 10,
            ])
            ->all();

        return [
            'key' => 'cms-poll-'.$content->id,
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

    private function audioUrl(EditorialContent $content): ?string
    {
        foreach (['audio_url', 'preview_audio_url', 'external_audio_url', 'stream_url', 'preview_url'] as $key) {
            $value = trim((string) $this->metadata($content, $key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        $assetId = $this->metadata($content, 'audio_asset_id')
            ?: collect($this->metadata($content, 'tracks', []))
                ->pluck('track_audio_asset_id')
                ->filter()
                ->first();

        $asset = $content->mediaAssets
            ->when($assetId, fn (Collection $assets): Collection => $assets->where('id', (int) $assetId))
            ->first(fn (MediaAsset $asset): bool => $asset->type === MediaAssetType::Audio);

        return $asset?->publicUrl();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function musicPlaybackQueue(EditorialContent $content, ?User $user): array
    {
        if (in_array($content->type, self::MUSIC_PLAYLIST_TYPES, true)) {
            return $this->playlistPlaybackQueue($content, $user);
        }

        if (in_array($content->type, self::MUSIC_ALBUM_TYPES, true)) {
            return $this->albumPlaybackQueue($content);
        }

        $track = $this->singlePlaybackQueueTrack($content);

        return $track === null ? [] : [$track];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function albumPlaybackQueue(EditorialContent $content): array
    {
        $imageUrl = $this->mediaUrl($content, ['album_artwork_asset_id', 'cover_asset_id', 'image_asset_id']);
        $tracks = collect($this->metadata($content, 'tracks', []))
            ->values()
            ->map(function (mixed $track, int $index) use ($content, $imageUrl): ?array {
                if (! is_array($track)) {
                    return null;
                }

                $title = trim((string) ($track['track_name'] ?? '')) ?: 'Track '.($index + 1);
                $audioUrl = $this->audioAssetUrl($content, data_get($track, 'track_audio_asset_id'))
                    ?: ($index === 0 ? $this->audioUrl($content) : null);

                if ($audioUrl === null) {
                    return null;
                }

                return $this->queueTrackPayload(
                    content: $content,
                    id: $content->getKey().':'.$index,
                    title: $content->title.' - '.$title,
                    audioUrl: $audioUrl,
                    imageUrl: $imageUrl,
                    itemType: 'track',
                );
            })
            ->filter()
            ->values()
            ->all();

        if ($tracks !== []) {
            return $tracks;
        }

        $track = $this->singlePlaybackQueueTrack(
            content: $content,
            title: $content->title,
            imageUrl: $imageUrl,
            itemType: 'album',
        );

        return $track === null ? [] : [$track];
    }

    private function singlePlaybackQueueTrack(
        EditorialContent $content,
        ?string $title = null,
        ?string $imageUrl = null,
        string $itemType = 'single',
    ): ?array {
        $audioUrl = $this->audioUrl($content);

        if ($audioUrl === null) {
            return null;
        }

        return $this->queueTrackPayload(
            content: $content,
            id: (string) $content->getKey(),
            title: $title ?: $content->title,
            audioUrl: $audioUrl,
            imageUrl: $imageUrl ?: $this->mediaUrl($content, ['artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
            itemType: $itemType,
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function playlistPlaybackQueue(EditorialContent $content, ?User $user): array
    {
        return collect($this->metadata($content, 'tracks', []))
            ->map(fn (mixed $reference): ?array => is_string($reference)
                ? $this->playlistPlaybackTrack($reference, $user)
                : null)
            ->filter()
            ->values()
            ->all();
    }

    private function playlistPlaybackTrack(string $reference, ?User $user): ?array
    {
        $parts = explode(':', $reference);

        if (count($parts) < 2) {
            return null;
        }

        if ($parts[0] === 'song') {
            $song = $this->playbackReferenceContent((int) $parts[1], self::MUSIC_SINGLE_TYPES);

            if (! $song || ! $this->canPlayReferencedMusic($song, $user)) {
                return null;
            }

            return $this->singlePlaybackQueueTrack($song);
        }

        if ($parts[0] !== 'album' || count($parts) !== 3) {
            return null;
        }

        $album = $this->playbackReferenceContent((int) $parts[1], self::MUSIC_ALBUM_TYPES);

        if (! $album || ! $this->canPlayReferencedMusic($album, $user)) {
            return null;
        }

        $index = (int) $parts[2];
        $track = data_get($album->metadata ?? [], "tracks.{$index}");

        if (! is_array($track)) {
            return null;
        }

        $title = trim((string) ($track['track_name'] ?? '')) ?: 'Track '.($index + 1);
        $audioUrl = $this->audioAssetUrl($album, data_get($track, 'track_audio_asset_id'));

        if ($audioUrl === null) {
            return null;
        }

        return $this->queueTrackPayload(
            content: $album,
            id: $album->getKey().':'.$index,
            title: $album->title.' - '.$title,
            audioUrl: $audioUrl,
            imageUrl: $this->mediaUrl($album, ['album_artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
            itemType: 'track',
        );
    }

    /**
     * @param  array<int, ContentType>  $types
     */
    private function playbackReferenceContent(int $id, array $types): ?EditorialContent
    {
        return EditorialContent::query()
            ->with([
                'mediaAssets' => fn ($query) => $query->orderBy('content_media_assets.sort_order'),
                'releaseWindows',
            ])
            ->whereKey($id)
            ->whereIn('type', array_map(fn (ContentType $type): string => $type->value, $types))
            ->first();
    }

    private function canPlayReferencedMusic(EditorialContent $content, ?User $user): bool
    {
        return $this->isPubliclyListable($content)
            && ($this->musicAccessPayload($content, $user)['state'] ?? null) === 'ready';
    }

    private function audioAssetUrl(EditorialContent $content, mixed $assetId): ?string
    {
        if (blank($assetId)) {
            return null;
        }

        $asset = $content->mediaAssets
            ->where('id', (int) $assetId)
            ->first(fn (MediaAsset $asset): bool => $asset->type === MediaAssetType::Audio);

        return $asset?->publicUrl();
    }

    /**
     * @return array<string, string>
     */
    private function queueTrackPayload(
        EditorialContent $content,
        string $id,
        string $title,
        string $audioUrl,
        ?string $imageUrl,
        string $itemType,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'audio_url' => $audioUrl,
            'image_url' => $imageUrl ?: '',
            'detail_url' => route('public.content.show', $content),
            'item_type' => $itemType,
            'state' => 'ready',
            'access_label' => '',
            'message' => $title,
        ];
    }

    private function listableMusicContents(string $section): Builder
    {
        $types = match ($section) {
            'albums' => self::MUSIC_ALBUM_TYPES,
            'singles' => self::MUSIC_SINGLE_TYPES,
            'playlists' => self::MUSIC_PLAYLIST_TYPES,
            default => [...self::MUSIC_ALBUM_TYPES, ...self::MUSIC_SINGLE_TYPES, ...self::MUSIC_PLAYLIST_TYPES],
        };

        return EditorialContent::query()
            ->with([
                'mediaAssets' => fn ($query) => $query->orderBy('content_media_assets.sort_order'),
                'releaseWindows',
            ])
            ->whereIn('type', array_map(fn (ContentType $type): string => $type->value, $types))
            ->where(fn (Builder $query): Builder => $this->applyPublishedNowConstraint($query))
            ->orderByRaw('COALESCE(published_at, scheduled_at, created_at) DESC');
    }

    private function applyPublishedNowConstraint(Builder $query): Builder
    {
        $now = now();

        return $query
            ->whereIn('status', [EditorialStatus::Published->value, EditorialStatus::Scheduled->value])
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->where(function (Builder $query) use ($now): void {
                        $query
                            ->where('status', EditorialStatus::Published->value)
                            ->where(function (Builder $query) use ($now): void {
                                $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $now);
                            });
                    })
                    ->orWhere(function (Builder $query) use ($now): void {
                        $query
                            ->where('status', EditorialStatus::Scheduled->value)
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', $now);
                    });
            });
    }

    private function isMusicContent(EditorialContent $content): bool
    {
        return in_array($content->type, [...self::MUSIC_ALBUM_TYPES, ...self::MUSIC_SINGLE_TYPES, ...self::MUSIC_PLAYLIST_TYPES], true);
    }

    private function isPubliclyListable(EditorialContent $content): bool
    {
        if (! in_array($content->status, [EditorialStatus::Published, EditorialStatus::Scheduled], true)) {
            return false;
        }

        $scheduledAt = $content->scheduled_at;

        if ($content->status === EditorialStatus::Published) {
            return $scheduledAt === null || $scheduledAt->lte(now());
        }

        return $scheduledAt !== null && $scheduledAt->lte(now());
    }

    /**
     * @return array<string, mixed>
     */
    private function musicBasePayload(EditorialContent $content, ?User $user): array
    {
        $access = $this->musicAccessPayload($content, $user);

        return [
            'id' => (string) $content->getKey(),
            'visibility' => $content->visibility->value,
            'summary' => $content->summary ?: $content->body ?: '',
            'detail_url' => route('public.content.show', $content),
            'play_url' => route('music.play', $content),
            'image_url' => match ($content->type) {
                ContentType::Song => $this->mediaUrl($content, ['artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
                ContentType::MusicalAlbum, ContentType::DeluxeAlbum => $this->mediaUrl($content, ['album_artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
                ContentType::MusicPlaylist => $this->mediaUrl($content, ['playlist_cover_asset_id', 'image_asset_id', 'cover_asset_id']),
                default => $this->mediaUrl($content, ['image_asset_id', 'cover_asset_id']),
            },
            'tracks' => $content->type === ContentType::MusicPlaylist ? $this->playlistTracks($content) : $this->tracklist($content),
            'has_audio_source' => $this->audioUrl($content) !== null,
            ...$access,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function musicAccessPayload(EditorialContent $content, ?User $user): array
    {
        if ($content->isVisibleTo($user)) {
            return [
                'state' => 'ready',
                'access_state' => 'ready',
                'access_label' => match ($content->visibility) {
                    VisibilityAudience::Open => 'Open',
                    VisibilityAudience::Member => 'Member',
                    VisibilityAudience::Royal => 'Royal',
                    VisibilityAudience::Purchased => 'Unlocked',
                },
                'access_message' => 'Ready for this account.',
                'cta_label' => null,
                'cta_url' => null,
            ];
        }

        if ($user === null) {
            return [
                'state' => 'login_required',
                'access_state' => 'login_required',
                'access_label' => 'Login required',
                'access_message' => 'Sign in to check access for this music item.',
                'cta_label' => 'Sign in',
                'cta_url' => route('login'),
            ];
        }

        if ($content->visibility === VisibilityAudience::Royal) {
            return [
                'state' => 'royal_required',
                'access_state' => 'royal_required',
                'access_label' => 'Royal required',
                'access_message' => 'This music item requires an active Royal Pass.',
                'cta_label' => 'Get Royal Pass',
                'cta_url' => route('store'),
            ];
        }

        if ($content->visibility === VisibilityAudience::Purchased) {
            return [
                'state' => 'content_locked',
                'access_state' => 'content_locked',
                'access_label' => 'Locked',
                'access_message' => 'This music item unlocks after purchase.',
                'cta_label' => 'Open store',
                'cta_url' => route('store'),
            ];
        }

        return [
            'state' => 'content_locked',
            'access_state' => 'content_locked',
            'access_label' => 'Locked',
            'access_message' => 'This release window is not open for this account.',
            'cta_label' => 'View details',
            'cta_url' => route('public.content.show', $content),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function tracklist(EditorialContent $content): array
    {
        $tracks = collect($this->metadata($content, 'tracks', []))
            ->map(fn (mixed $track): string => is_array($track) ? trim((string) ($track['track_name'] ?? '')) : trim((string) $track))
            ->filter()
            ->values();

        if ($tracks->isNotEmpty()) {
            return $tracks->all();
        }

        return collect(preg_split('/\R/', (string) $this->metadata($content, 'tracklist', '')) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function playlistTracks(EditorialContent $content): array
    {
        return collect($this->metadata($content, 'tracks', []))
            ->map(fn (mixed $reference): ?string => is_string($reference) ? $this->playlistTrackLabel($reference) : null)
            ->filter()
            ->values()
            ->all();
    }

    private function playlistTrackLabel(string $reference): ?string
    {
        $parts = explode(':', $reference);

        if (count($parts) < 2) {
            return null;
        }

        if ($parts[0] === 'song') {
            return EditorialContent::query()
                ->whereKey((int) $parts[1])
                ->where('type', ContentType::Song->value)
                ->value('title');
        }

        if ($parts[0] !== 'album' || count($parts) !== 3) {
            return null;
        }

        $album = EditorialContent::query()
            ->whereKey((int) $parts[1])
            ->where('type', ContentType::MusicalAlbum->value)
            ->first();

        if (! $album) {
            return null;
        }

        $index = (int) $parts[2];
        $trackName = data_get($album->metadata ?? [], "tracks.{$index}.track_name");

        if (blank($trackName)) {
            $trackName = collect(preg_split('/\R/', (string) data_get($album->metadata ?? [], 'tracklist', '')) ?: [])
                ->map(fn (string $line): string => trim($line))
                ->filter()
                ->values()
                ->get($index);
        }

        return filled($trackName) ? $album->title.' - '.$trackName : null;
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
