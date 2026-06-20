<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\SitePageSetting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StorefrontSettingsService
{
    public const PAGE = 'store';

    public const SECTION = 'storefront';

    /**
     * @var array<int, string>
     */
    private const SLOT_KEYS = [
        'event_primary',
        'event_secondary',
        'album',
        'merch',
    ];

    /**
     * @return array<int, string>
     */
    public static function slotKeys(): array
    {
        return self::SLOT_KEYS;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'royal_pass' => [
                'copy_before' => 'Get your',
                'emphasis' => 'Royal Pass',
                'copy_after' => 'to unlock exclusive content, community and more',
                'cta_label' => 'BUY HERE',
                'product_key' => 'royal',
            ],
            'slots' => [
                'event_primary' => [
                    'key' => 'event_primary',
                    'kind' => 'event',
                    'title' => 'Reny Renteria en Concierto',
                    'eyebrow' => '',
                    'description' => "Rock & Folk Pty, Ciudad de Panama\n21/ Sep - 7:30 PM",
                    'price_label' => 'FREE',
                    'cta_label' => 'GET TICKETS',
                    'countdown_at' => '2026-09-21 19:30:00',
                    'action_type' => 'rsvp',
                    'product_key' => 'concert',
                    'url' => '',
                    'image' => 'images/store/reny-concert.png',
                    'image_asset_id' => null,
                    'content_id' => null,
                    'image_alt' => 'Reny Renteria concert poster',
                ],
                'event_secondary' => [
                    'key' => 'event_secondary',
                    'kind' => 'event',
                    'title' => 'Festival de la Rosa Dorada',
                    'eyebrow' => '',
                    'description' => "Rock & Folk Pty, Ciudad de Panama\n19/ Dic - 7:30 PM",
                    'price_label' => '$15',
                    'cta_label' => 'GET TICKETS',
                    'countdown_at' => '2026-12-19 19:30:00',
                    'action_type' => 'buy',
                    'product_key' => 'listening',
                    'url' => '',
                    'image' => 'images/store/rosa-dorada.png',
                    'image_asset_id' => null,
                    'content_id' => null,
                    'image_alt' => 'Festival de la Rosa Dorada poster',
                ],
                'album' => [
                    'key' => 'album',
                    'kind' => 'album',
                    'title' => 'Work in Progress',
                    'eyebrow' => 'Deluxe Album',
                    'description' => 'Includes: Tracks, BTS, Notes, Videos',
                    'price_label' => '',
                    'cta_label' => 'GET DELUXE',
                    'action_type' => 'buy',
                    'product_key' => 'deluxe',
                    'url' => '',
                    'image' => 'images/store/work-in-progress.png',
                    'image_asset_id' => null,
                    'content_id' => null,
                    'image_alt' => 'Work in Progress deluxe album artwork',
                ],
                'merch' => [
                    'key' => 'merch',
                    'kind' => 'merch',
                    'title' => 'Crown Collection',
                    'eyebrow' => '',
                    'description' => 'Limited edition merch package',
                    'price_label' => '',
                    'cta_label' => 'GET MERCH',
                    'action_type' => 'buy',
                    'product_key' => 'merch',
                    'url' => '',
                    'image' => 'images/store/crown-collection.png',
                    'image_asset_id' => null,
                    'content_id' => null,
                    'image_alt' => 'Crown Collection jacket and CD bundle',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editorPayload(): array
    {
        $draft = $this->draftSetting();
        $published = $this->publishedSetting();
        $active = $draft ?? $published;

        return [
            ...$this->payloadFor($active),
            '_editor_status' => $draft ? SitePageSetting::STATUS_DRAFT : ($published ? SitePageSetting::STATUS_PUBLISHED : SitePageSetting::STATUS_DRAFT),
            '_has_draft' => $draft !== null,
            '_has_published' => $published !== null,
            '_published_at' => $published?->published_at,
            '_updated_at' => $active?->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        return [
            ...$this->payloadFor($this->publishedSetting()),
            '_editor_status' => SitePageSetting::STATUS_PUBLISHED,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function save(User $actor, array $payload, string $status): SitePageSetting
    {
        $status = $status === SitePageSetting::STATUS_PUBLISHED
            ? SitePageSetting::STATUS_PUBLISHED
            : SitePageSetting::STATUS_DRAFT;

        $attributes = [
            'payload' => $this->normalize($payload),
            'media_asset_id' => null,
            'updated_by_id' => $actor->id,
        ];

        if ($status === SitePageSetting::STATUS_PUBLISHED) {
            $attributes = [
                ...$attributes,
                'published_by_id' => $actor->id,
                'published_at' => Carbon::now(),
            ];
        }

        $setting = SitePageSetting::query()->updateOrCreate([
            'page' => self::PAGE,
            'section' => self::SECTION,
            'status' => $status,
        ], $attributes);

        if ($status === SitePageSetting::STATUS_PUBLISHED) {
            $this->draftSetting()?->delete();
        }

        return $setting->fresh();
    }

    public function draftSetting(): ?SitePageSetting
    {
        return SitePageSetting::query()
            ->forSection(self::PAGE, self::SECTION)
            ->draft()
            ->first();
    }

    public function publishedSetting(): ?SitePageSetting
    {
        return SitePageSetting::query()
            ->forSection(self::PAGE, self::SECTION)
            ->published()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(?SitePageSetting $setting): array
    {
        return $this->resolveLinkedContent($this->normalize($setting?->payload ?? []));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolveLinkedContent(array $payload): array
    {
        $slots = $payload['slots'];
        $assetIds = collect($slots)
            ->pluck('image_asset_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $assets = $assetIds->isEmpty()
            ? collect()
            : MediaAsset::query()->whereKey($assetIds)->get()->keyBy('id');

        $album = $this->publishedAlbum((int) ($slots['album']['content_id'] ?? 0));

        if ($album instanceof EditorialContent) {
            $slots['album'] = $this->albumSlot($slots['album'], $album);
        }

        $slots = collect($slots)
            ->map(function (array $slot) use ($assets): array {
                $asset = $assets->get((int) ($slot['image_asset_id'] ?? 0));

                if ($asset instanceof MediaAsset) {
                    $slot['image_url'] = $asset->publicUrl();
                    $slot['image_alt'] = $asset->alt_text ?: $slot['image_alt'];
                }

                return $slot;
            })
            ->all();

        return [
            ...$payload,
            'slots' => $slots,
        ];
    }

    private function publishedAlbum(int $contentId): ?EditorialContent
    {
        if ($contentId <= 0) {
            return null;
        }

        $now = now();

        return EditorialContent::query()
            ->with(['mediaAssets'])
            ->whereKey($contentId)
            ->whereIn('type', [ContentType::MusicalAlbum->value, ContentType::DeluxeAlbum->value])
            ->whereIn('status', [EditorialStatus::Published->value, EditorialStatus::Scheduled->value])
            ->where(function ($query) use ($now): void {
                $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $now);
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array<string, mixed>
     */
    private function albumSlot(array $slot, EditorialContent $album): array
    {
        $assetId = (int) ($slot['image_asset_id'] ?? 0);
        $asset = $album->mediaAssets
            ->when($assetId > 0, fn (Collection $assets): Collection => $assets->where('id', $assetId))
            ->first();

        return [
            ...$slot,
            'title' => $album->title,
            'eyebrow' => $album->type === ContentType::DeluxeAlbum ? 'Deluxe Album' : 'Album',
            'description' => $album->summary ?: $slot['description'],
            'product_key' => $album->purchase_key ?: $slot['product_key'],
            'image_url' => $asset?->publicUrl() ?? ($slot['image_url'] ?? null),
            'image_alt' => $asset?->alt_text ?: $slot['image_alt'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        $defaults = $this->defaults();

        $royalPass = collect($defaults['royal_pass'])
            ->mapWithKeys(fn (string $default, string $key): array => [
                $key => $this->stringValue(Arr::get($payload, "royal_pass.{$key}"), $default),
            ])
            ->all();

        $slots = [];

        foreach (self::SLOT_KEYS as $slotKey) {
            $slotDefaults = $defaults['slots'][$slotKey];
            $slotPayload = Arr::get($payload, "slots.{$slotKey}", []);

            $slot = collect($slotDefaults)
                ->mapWithKeys(function (mixed $default, string $key) use ($slotPayload): array {
                    $value = Arr::get($slotPayload, $key);

                    if (in_array($key, ['image_asset_id', 'content_id'], true)) {
                        return [$key => is_numeric($value) && (int) $value > 0 ? (int) $value : null];
                    }

                    if ($key === 'action_type') {
                        return [$key => in_array($value, ['buy', 'rsvp', 'link'], true) ? $value : $default];
                    }

                    return [$key => $this->stringValue($value, is_scalar($default) ? (string) $default : '')];
                })
                ->all();

            $slot['key'] = $slotKey;
            $slot['kind'] = $slotDefaults['kind'];
            $slots[$slotKey] = $slot;
        }

        return [
            'royal_pass' => $royalPass,
            'slots' => $slots,
        ];
    }

    private function stringValue(mixed $value, string $default): string
    {
        if (! is_scalar($value)) {
            return $default;
        }

        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }
}
