<?php

namespace App\Services\PublicCms;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\StorefrontSettingsService;
use Throwable;

class HomePayloadBuilder
{
    public function __construct(
        private readonly ContentQuery $contentQuery,
        private readonly MusicPayloadBuilder $musicPayloads,
        private readonly StorefrontSettingsService $storefrontSettings,
        private readonly VideoPayloadBuilder $videoPayloads,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?User $user): array
    {
        $storefront = $this->storefrontSettings->publicPayload($user);
        $featuredVideo = $this->videoPayloads->featuredFrom(
            $this->contentQuery->visibleContents($user, [ContentType::Video], null)->get()
        );
        $selectedAlbumId = (int) data_get($storefront, 'slots.album.content_id', 0);
        $latestAlbum = $selectedAlbumId > 0
            ? $this->contentQuery->albumContentById($selectedAlbumId)
            : $this->contentQuery->latestAlbumContent();

        return [
            'featured_video' => $featuredVideo,
            'storefront' => $storefront,
            'events' => collect(['event_primary', 'event_secondary'])
                ->map(fn (string $key): array => data_get($storefront, "slots.{$key}", []))
                ->filter()
                ->values()
                ->all(),
            'album' => $this->homeAlbum(
                $latestAlbum instanceof EditorialContent ? $this->musicPayloads->album($latestAlbum, 0, $user) : null,
                data_get($storefront, 'slots.album', []),
            ),
            'singles' => $this->safeSingles($user),
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
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function safeSingles(?User $user): array
    {
        try {
            return $this->singles($user);
        } catch (Throwable $exception) {
            report($exception);
        }

        if ($user !== null) {
            try {
                return $this->singles(null);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function singles(?User $user): array
    {
        return $this->contentQuery->listableMusicContents('singles')
            ->limit(3)
            ->get()
            ->values()
            ->map(fn (EditorialContent $content): array => $this->musicPayloads->single($content, $user))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $storeAlbum
     * @return array<string, mixed>|null
     */
    private function homeAlbum(?array $album, array $storeAlbum): ?array
    {
        if (! is_array($album)) {
            return $storeAlbum === []
                ? null
                : [
                    ...$storeAlbum,
                    'summary' => $storeAlbum['description'] ?? '',
                    '_storefront_slot' => true,
                ];
        }

        $storeContentId = (int) ($storeAlbum['content_id'] ?? 0);
        $isStorefrontSlot = $storeContentId > 0 && $storeContentId === (int) ($album['id'] ?? 0);

        return [
            ...$album,
            'summary' => $album['summary'] ?? $storeAlbum['description'] ?? '',
            'image_url' => $album['image_url'] ?? null,
            'store_image' => $storeAlbum['image'] ?? null,
            'image_alt' => $storeAlbum['image_alt'] ?? $album['title'],
            ...($isStorefrontSlot ? [
                'action_type' => $storeAlbum['action_type'] ?? null,
                'product_key' => $storeAlbum['product_key'] ?? null,
                'cta_label' => $storeAlbum['cta_label'] ?? null,
                'url' => $storeAlbum['url'] ?? ($album['url'] ?? null),
            ] : []),
            '_storefront_slot' => $isStorefrontSlot && ($storeAlbum['action_type'] ?? null) === 'buy',
        ];
    }
}
