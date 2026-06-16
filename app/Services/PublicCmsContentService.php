<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class PublicCmsContentService
{
    public function music(?User $user = null): array
    {
        return $this->load('music', $user, [
            ContentType::Song->value,
            ContentType::MusicalAlbum->value,
            ContentType::DeluxeAlbum->value,
            ContentType::Exclusive->value,
        ], fn (Collection $contents): array => [
            'albums' => $contents
                ->filter(fn (EditorialContent $content): bool => in_array($content->type, [ContentType::MusicalAlbum, ContentType::DeluxeAlbum], true))
                ->map(fn (EditorialContent $content): array => $this->albumPayload($content))
                ->values()
                ->all(),
            'singles' => $contents
                ->filter(fn (EditorialContent $content): bool => in_array($content->type, [ContentType::Song, ContentType::Exclusive], true))
                ->map(fn (EditorialContent $content): array => $this->singlePayload($content))
                ->values()
                ->all(),
        ]);
    }

    public function videos(?User $user = null): array
    {
        return $this->load('videos', $user, [ContentType::Video->value], function (Collection $contents): array {
            $groups = [
                'musicVideos' => [],
                'series' => [],
                'performances' => [],
                'behindTheScenes' => [],
                'vlogs' => [],
            ];

            $contents->each(function (EditorialContent $content) use (&$groups): void {
                $groups[$this->videoBucket($content)][] = $this->videoPayload($content);
            });

            return $groups;
        });
    }

    public function photos(?User $user = null): array
    {
        return $this->load('photos', $user, [
            ContentType::Photo->value,
            ContentType::Gallery->value,
        ], fn (Collection $contents): array => [
            'photos' => $contents
                ->map(fn (EditorialContent $content, int $index): array => $this->photoPayload($content, $index))
                ->values()
                ->all(),
        ]);
    }

    public function store(?User $user = null): array
    {
        return $this->load('store', $user, [
            ContentType::Product->value,
            ContentType::Event->value,
            ContentType::Drop->value,
        ], function (Collection $contents): array {
            $products = $contents
                ->filter(fn (EditorialContent $content): bool => in_array($content->type, [ContentType::Product, ContentType::Drop], true))
                ->map(fn (EditorialContent $content): array => $this->productPayload($content))
                ->values()
                ->all();
            $events = $contents
                ->filter(fn (EditorialContent $content): bool => $content->type === ContentType::Event)
                ->map(fn (EditorialContent $content): array => $this->eventPayload($content))
                ->values()
                ->all();

            return [
                'products' => $products,
                'events' => $events,
                'heroEvent' => $events[0] ?? null,
                'storePrices' => collect([...$products, ...$events])
                    ->mapWithKeys(fn (array $item): array => [$item['key'] => (float) $item['price']])
                    ->all(),
            ];
        });
    }

    public function community(?User $user = null): array
    {
        return $this->load('community', $user, [
            ContentType::Post->value,
            ContentType::Poll->value,
        ], fn (Collection $contents): array => [
            'communityPosts' => $contents
                ->filter(fn (EditorialContent $content): bool => $content->type === ContentType::Post)
                ->map(fn (EditorialContent $content): array => $this->postPayload($content))
                ->values()
                ->all(),
            'communityPolls' => $contents
                ->filter(fn (EditorialContent $content): bool => $content->type === ContentType::Poll)
                ->map(fn (EditorialContent $content): array => $this->pollPayload($content))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @param  array<int, string>  $types
     */
    private function load(string $page, ?User $user, array $types, callable $transform): array
    {
        try {
            if (! Schema::hasTable('editorial_contents')) {
                return $this->cachedSnapshot($page, $user);
            }

            $contents = EditorialContent::query()
                ->with(['mediaAssets', 'releaseWindows', 'taxonomies'])
                ->visibleFor($user)
                ->whereIn('type', $types)
                ->orderByDesc('published_at')
                ->orderByDesc('scheduled_at')
                ->orderByDesc('created_at')
                ->limit(60)
                ->get();

            if ($contents->isEmpty()) {
                return [];
            }

            $payload = $transform($contents);

            if ($this->payloadIsEmpty($payload)) {
                return [];
            }

            $payload['_cms'] = [
                'source' => 'cms',
                'generated_at' => now()->toISOString(),
            ];

            Cache::forever($this->cacheKey($page, $user), $payload);

            return $payload;
        } catch (Throwable $throwable) {
            report($throwable);

            $payload = $this->cachedSnapshot($page, $user);

            return $payload === [] ? [] : [
                ...$payload,
                '_cms' => [
                    ...($payload['_cms'] ?? []),
                    'source' => 'cache',
                    'fallback_reason' => 'cms_unavailable',
                ],
            ];
        }
    }

    private function cachedSnapshot(string $page, ?User $user): array
    {
        $payload = Cache::get($this->cacheKey($page, $user));

        return is_array($payload) ? $payload : [];
    }

    private function cacheKey(string $page, ?User $user): string
    {
        if ($user === null) {
            return "public_cms.snapshots.{$page}.guest";
        }

        return "public_cms.snapshots.{$page}.user.{$user->getKey()}";
    }

    private function albumPayload(EditorialContent $content): array
    {
        $tracklist = (string) Arr::get($content->metadata ?? [], 'tracklist', '');
        $trackCount = collect(preg_split('/\R+/', $tracklist) ?: [])
            ->filter(fn (string $track): bool => trim($track) !== '')
            ->count();

        return [
            'title' => $content->title,
            'tracks' => $trackCount > 0 ? "{$trackCount} tracks" : ($content->summary ?? 'Album'),
            'cover_url' => $this->mediaUrl($content, ['cover_asset_id']),
            'cover_class' => 'cover-a',
            'cover_label' => Str::limit($content->title, 12, ''),
            'url' => route('content.show', ['type' => $content->type->value, 'slug' => $content->slug]),
        ];
    }

    private function singlePayload(EditorialContent $content): array
    {
        return [
            'title' => $content->title,
            'artist' => 'Reny Renteria',
            'summary' => $content->summary ?? 'Reny Renteria',
            'art_url' => $this->mediaUrl($content, ['cover_asset_id']),
            'url' => route('content.show', ['type' => $content->type->value, 'slug' => $content->slug]),
        ];
    }

    private function videoPayload(EditorialContent $content): array
    {
        $youtubeId = $this->youtubeId((string) Arr::get($content->metadata ?? [], 'youtube_url'));

        return [
            'id' => $youtubeId,
            'title' => e($content->title),
            'meta' => e($content->summary ?? Arr::get($content->metadata ?? [], 'category', 'Video')),
            'url' => route('content.show', ['type' => $content->type->value, 'slug' => $content->slug]),
        ];
    }

    private function videoBucket(EditorialContent $content): string
    {
        $metadata = $content->metadata ?? [];
        $category = Str::of((string) Arr::get($metadata, 'category', ''))->lower()->replace(['_', ' '], '-')->toString();

        if (str_contains($category, 'series') || str_contains($category, 'playlist')) {
            return 'series';
        }

        if (str_contains($category, 'performance') || str_contains($category, 'live')) {
            return 'performances';
        }

        if (str_contains($category, 'behind') || str_contains($category, 'studio')) {
            return 'behindTheScenes';
        }

        if (str_contains($category, 'vlog')) {
            return 'vlogs';
        }

        return 'musicVideos';
    }

    private function photoPayload(EditorialContent $content, int $index): array
    {
        $metadata = $content->metadata ?? [];

        return [
            'image_url' => $this->mediaUrl($content, ['image_asset_id']),
            'image' => $this->fallbackPhoto($index),
            'type' => $content->type === ContentType::Gallery ? 'Album' : 'Single post',
            'tone' => Str::slug((string) Arr::get($metadata, 'location', 'gallery')) ?: 'gallery',
            'title' => $content->title,
            'caption' => (string) Arr::get($metadata, 'caption', $content->summary ?? ''),
            'size' => ['wide', 'tall', 'standard'][$index % 3],
        ];
    }

    private function productPayload(EditorialContent $content): array
    {
        $metadata = $content->metadata ?? [];
        $kind = (string) Arr::get($metadata, 'product_kind', Arr::get($metadata, 'drop_kind', 'digital'));
        $price = ((int) Arr::get($metadata, 'price_cents', 0)) / 100;
        $inventory = Arr::get($metadata, 'inventory');

        return [
            'key' => $content->purchase_key ?? Arr::get($metadata, 'sku') ?? $content->slug,
            'name' => $content->title,
            'type' => Str::headline($kind),
            'category' => $this->productCategory($kind),
            'price' => $price,
            'availability' => $inventory === null ? 'Instant access' : "{$inventory} available",
            'points' => '+'.max(0, (int) floor($price * 10)).' pts',
            'pass' => $content->visibility->value === 'open' ? 'No Royal Pass required' : 'Access controlled',
            'access' => $content->purchase_key ? 'Unlocks in profile' : 'Available after checkout',
            'image_url' => $this->mediaUrl($content, ['image_asset_id', 'cover_asset_id']),
            'image' => 'cover.jpg',
            'summary' => $content->summary ?? $content->body ?? '',
            'suffix' => $kind === 'subscription' ? '/mo' : null,
        ];
    }

    private function eventPayload(EditorialContent $content): array
    {
        $metadata = $content->metadata ?? [];
        $price = ((int) Arr::get($metadata, 'price_cents', 0)) / 100;

        return [
            'key' => $content->purchase_key ?? $content->slug,
            'name' => $content->title,
            'kicker' => Str::headline((string) Arr::get($metadata, 'event_kind', 'Event')),
            'date' => rescue(
                fn (): string => CarbonImmutable::parse((string) Arr::get($metadata, 'starts_at'))->format('M d, Y'),
                'Scheduled event',
                report: false
            ),
            'place' => (string) Arr::get($metadata, 'location', 'Online'),
            'price' => $price,
            'image_url' => $this->mediaUrl($content, ['image_asset_id', 'cover_asset_id']),
            'image' => 'studio.jpg',
            'action' => Arr::get($metadata, 'ticketing_mode') === 'rsvp' ? 'RSVP' : 'Buy ticket',
            'mode' => Arr::get($metadata, 'ticketing_mode') === 'rsvp' ? 'rsvp' : 'buy',
        ];
    }

    private function postPayload(EditorialContent $content): array
    {
        return [
            'title' => $content->title,
            'time' => $content->published_at?->diffForHumans() ?? 'Published',
            'body' => $content->body ?? $content->summary ?? '',
            'image_url' => $this->mediaUrl($content, ['image_asset_id']),
            'url' => route('content.show', ['type' => $content->type->value, 'slug' => $content->slug]),
        ];
    }

    private function pollPayload(EditorialContent $content): array
    {
        $options = collect(Arr::get($content->metadata ?? [], 'options', []))
            ->values()
            ->map(fn (string $option, int $index): array => [
                'label' => $option,
                'percent' => [42, 34, 24, 18, 12, 8, 6, 4][$index] ?? 1,
            ])
            ->all();

        return [
            'question' => (string) Arr::get($content->metadata ?? [], 'question', $content->title),
            'options' => $options,
            'results_visibility' => Arr::get($content->metadata ?? [], 'results_visibility', 'public'),
        ];
    }

    /**
     * @param  array<int, string>  $metadataAssetKeys
     */
    private function mediaUrl(EditorialContent $content, array $metadataAssetKeys): ?string
    {
        $asset = $this->metadataAsset($content, $metadataAssetKeys)
            ?? $content->mediaAssets->first(fn (MediaAsset $asset): bool => $asset->publicUrl() !== null);

        return $asset?->publicUrl();
    }

    /**
     * @param  array<int, string>  $metadataAssetKeys
     */
    private function metadataAsset(EditorialContent $content, array $metadataAssetKeys): ?MediaAsset
    {
        foreach ($metadataAssetKeys as $key) {
            $assetId = Arr::get($content->metadata ?? [], $key);

            if ($assetId === null) {
                continue;
            }

            $asset = $content->mediaAssets->firstWhere('id', (int) $assetId);

            if ($asset instanceof MediaAsset && $asset->publicUrl() !== null) {
                return $asset;
            }
        }

        return null;
    }

    private function youtubeId(string $url): string
    {
        $parts = parse_url($url);

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);

            if (! empty($query['v'])) {
                return (string) $query['v'];
            }
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        $segments = explode('/', $path);

        return end($segments) ?: $url;
    }

    private function fallbackPhoto(int $index): string
    {
        $photos = [
            'capri.jpg',
            'studio.jpg',
            'radio.jpg',
            'places.jpg',
            'tv.jpg',
            'performance.jpg',
            'rehearsal.jpg',
            'cover.jpg',
            'campaign.jpg',
            'merch.jpg',
            'dance.jpg',
            'tvVisit.jpg',
        ];

        return $photos[$index % count($photos)];
    }

    private function productCategory(string $kind): string
    {
        return match ($kind) {
            'physical' => 'physical merch',
            'drop' => 'drops physical',
            'subscription' => 'pass',
            'bundle' => 'drops digital',
            default => 'digital',
        };
    }

    private function payloadIsEmpty(array $payload): bool
    {
        return collect($payload)
            ->reject(fn (mixed $value, string $key): bool => str_starts_with($key, '_'))
            ->every(fn (mixed $value): bool => $value === null || $value === []);
    }
}
