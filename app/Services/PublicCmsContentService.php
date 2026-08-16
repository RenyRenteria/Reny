<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\PublicCms\AccessPayloadBuilder;
use App\Services\PublicCms\CommunityPayloadBuilder;
use App\Services\PublicCms\ContentQuery;
use App\Services\PublicCms\HomePayloadBuilder;
use App\Services\PublicCms\MusicPayloadBuilder;
use App\Services\PublicCms\PhotoPayloadBuilder;
use App\Services\PublicCms\StorePayloadBuilder;
use App\Services\PublicCms\VideoPayloadBuilder;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PublicCmsContentService
{
    private const PAGES = ['home', 'music', 'videos', 'photos', 'store', 'community'];

    private const VERSION_KEY = 'public-cms:last-published:version';

    private const USER_VERSION_PREFIX = 'public-cms:last-published:user-version:';

    public function __construct(
        private readonly AccessPayloadBuilder $accessPayloads,
        private readonly CmsPreviewContext $previewContext,
        private readonly CommunityPayloadBuilder $communityPayloads,
        private readonly ContentQuery $contentQuery,
        private readonly HomePayloadBuilder $homePayloads,
        private readonly MusicPayloadBuilder $musicPayloads,
        private readonly PageSettingsService $pageSettings,
        private readonly PhotoPayloadBuilder $photoPayloads,
        private readonly StorePayloadBuilder $storePayloads,
        private readonly VideoPayloadBuilder $videoPayloads,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function home(?User $user): array
    {
        $payload = $this->page('home', $user, fn (): array => [
            ...$this->homePayloads->build($user),
        ]);

        if (empty($payload['singles'])) {
            $payload['singles'] = $this->homePayloads->safeSingles($user);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function music(?User $user): array
    {
        return $this->page('music', $user, fn (): array => [
            ...$this->musicPayloads->index($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function musicCollection(?User $user, string $section): array
    {
        abort_unless(in_array($section, ['albums', 'singles', 'playlists'], true), 404);

        return $this->page("music-{$section}", $user, fn (): array => [
            ...$this->musicPayloads->collection($user, $section),
        ]);
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function musicPlayback(EditorialContent $content, ?User $user, ?int $track = null): array
    {
        return $this->musicPayloads->playback($content, $user, $track);
    }

    /**
     * @return array<string, mixed>
     */
    public function videos(?User $user): array
    {
        return $this->page('videos', $user, fn (): array => [
            ...$this->videoPayloads->build(
                $this->contentQuery->visibleContents($user, [ContentType::Video], null)->get()
            ),
            'page' => $this->pageSettings->publicPayload('videos'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function photos(?User $user): array
    {
        return $this->page('photos', $user, fn (): array => [
            ...$this->photoPayloads->build($user),
            'page' => $this->pageSettings->publicPayload('photos'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function store(?User $user): array
    {
        return $this->page('store', $user, fn (): array => [
            ...$this->storePayloads->build($user),
            'page' => $this->pageSettings->publicPayload('store'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function community(?User $user): array
    {
        return $this->page('community', $user, fn (): array => [
            ...$this->communityPayloads->build($user),
            'page' => $this->pageSettings->publicPayload('community'),
        ]);
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

    /**
     * @return array<string, mixed>
     */
    public function albumDetail(EditorialContent $content, ?User $user): array
    {
        return $this->musicPayloads->albumDetail($content, $user);
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
     * @return array<string, mixed>
     */
    private function page(string $page, ?User $user, callable $loader): array
    {
        if ($this->previewContext->active()) {
            try {
                return [
                    ...$loader(),
                    '_cms_source' => 'preview',
                    '_cms_fallback' => false,
                ];
            } catch (Throwable $exception) {
                report($exception);

                return [
                    '_cms_source' => 'preview',
                    '_cms_fallback' => true,
                ];
            }
        }

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
        $access = $this->accessPayloads->fingerprint($viewer);

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
}
