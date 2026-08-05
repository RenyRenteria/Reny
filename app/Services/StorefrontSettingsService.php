<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\SitePageSetting;
use App\Models\User;
use App\Services\Commerce\ProductCatalog;
use App\Services\PublicCms\ContentQuery;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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

    public function __construct(
        private readonly CmsPreviewContext $previewContext,
        private readonly ContentQuery $contentQuery,
        private readonly ProductCatalog $products,
    ) {}

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
                'cta_label' => 'Unlock Royal Pass',
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
                    'description' => 'Rock & Folk Pty, Ciudad de Panama',
                    'price_label' => '',
                    'cta_label' => 'GET TICKETS',
                    'countdown_at' => '',
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
                    'cta_label' => 'LISTEN',
                    'action_type' => 'link',
                    'product_key' => 'deluxe',
                    'url' => '/music',
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
    public function publicPayload(?User $user = null): array
    {
        $setting = $this->previewContext->active()
            ? ($this->draftSetting() ?? $this->publishedSetting())
            : $this->publishedSetting();
        $payload = $this->payloadFor($setting, $user, true);

        return [
            ...$payload,
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
        if (! $this->settingsAvailable()) {
            return null;
        }

        return SitePageSetting::query()
            ->forSection(self::PAGE, self::SECTION)
            ->draft()
            ->first();
    }

    public function publishedSetting(): ?SitePageSetting
    {
        if (! $this->settingsAvailable()) {
            return null;
        }

        return SitePageSetting::query()
            ->forSection(self::PAGE, self::SECTION)
            ->published()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(
        ?SitePageSetting $setting,
        ?User $user = null,
        bool $hideUnavailableLinkedContent = false,
    ): array {
        return $this->resolveLinkedContent(
            $this->normalize($setting?->payload ?? []),
            $user,
            $hideUnavailableLinkedContent,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolveLinkedContent(
        array $payload,
        ?User $user,
        bool $hideUnavailableLinkedContent,
    ): array {
        $slots = $payload['slots'];
        $assetIds = collect($slots)
            ->pluck('image_asset_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $assets = $assetIds->isEmpty() || ! $this->mediaAvailable()
            ? collect()
            : MediaAsset::query()
                ->ready()
                ->where('is_public', true)
                ->whereKey($assetIds)
                ->get()
                ->keyBy('id');

        $albumContentId = (int) ($slots['album']['content_id'] ?? 0);
        $album = $this->publishedAlbum($albumContentId, $user);

        if ($album instanceof EditorialContent) {
            $slots['album'] = $this->albumSlot($slots['album'], $album);
        } elseif ($hideUnavailableLinkedContent && $albumContentId > 0) {
            unset($slots['album']);
        }

        $linkedContents = $this->publishedCommerceContents($slots, $user);

        foreach (['event_primary', 'event_secondary', 'merch'] as $slotKey) {
            if (! isset($slots[$slotKey])) {
                continue;
            }

            $slot = $slots[$slotKey];
            $content = $linkedContents->first(function (EditorialContent $content) use ($slot): bool {
                $contentId = (int) ($slot['content_id'] ?? 0);

                return ($contentId > 0 && $content->id === $contentId)
                    || (filled($slot['product_key'] ?? null) && $content->purchase_key === $slot['product_key']);
            });

            if ($content instanceof EditorialContent) {
                $slots[$slotKey] = $this->commerceSlot($slot, $content);
            } elseif ($hideUnavailableLinkedContent && (int) ($slot['content_id'] ?? 0) > 0) {
                unset($slots[$slotKey]);
            } elseif (($slot['action_type'] ?? null) === 'buy') {
                $product = $this->products->find((string) ($slot['product_key'] ?? ''));

                if (is_array($product)) {
                    $slots[$slotKey] = $this->configuredCommerceSlot($slot, $product);
                } elseif ($hideUnavailableLinkedContent) {
                    unset($slots[$slotKey]);
                }
            }
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

    private function publishedAlbum(int $contentId, ?User $user): ?EditorialContent
    {
        if ($contentId <= 0 || ! $this->editorialContentAvailable()) {
            return null;
        }

        return $this->contentQuery
            ->visibleContents($user, [ContentType::MusicalAlbum, ContentType::DeluxeAlbum], null)
            ->whereKey($contentId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array<string, mixed>
     */
    private function albumSlot(array $slot, EditorialContent $album): array
    {
        $assetId = (int) ($slot['image_asset_id'] ?? 0);
        $asset = $album->relationLoaded('mediaAssets')
            ? $album->mediaAssets
                ->when($assetId > 0, fn (Collection $assets): Collection => $assets->where('id', $assetId))
                ->first()
            : null;

        $product = $this->products->forContent($album);

        return [
            ...$slot,
            'content_id' => $album->id,
            'title' => $album->title,
            'eyebrow' => $album->type === ContentType::DeluxeAlbum ? 'Deluxe Album' : 'Album',
            'description' => $album->summary ?: $slot['description'],
            'product_key' => $album->purchase_key ?: $slot['product_key'],
            'price_label' => $product
                ? $this->moneyLabel((int) $product['amount_cents'], (string) $product['currency'])
                : $slot['price_label'],
            'action_type' => $product ? 'buy' : 'link',
            'url' => $product ? '' : ($album->type === ContentType::MusicalAlbum
                ? route('music.albums.show', $album)
                : route('music')),
            'cta_label' => $product ? 'GET DELUXE' : 'LISTEN',
            'image_url' => $asset?->publicUrl() ?? ($slot['image_url'] ?? null),
            'image_alt' => $asset?->alt_text ?: $slot['image_alt'],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $slots
     * @return Collection<int, EditorialContent>
     */
    private function publishedCommerceContents(array $slots, ?User $user): Collection
    {
        if (! $this->editorialContentAvailable()) {
            return collect();
        }

        $contentIds = collect($slots)->pluck('content_id')->filter()->map(fn ($id): int => (int) $id);
        $purchaseKeys = collect($slots)->pluck('product_key')->filter();

        $query = $this->contentQuery
            ->visibleContents($user, [
                ContentType::Product,
                ContentType::Drop,
                ContentType::Exclusive,
                ContentType::Event,
            ], null)
            ->where(function ($query) use ($contentIds, $purchaseKeys): void {
                $query->whereIn('id', $contentIds)->orWhereIn('purchase_key', $purchaseKeys);
            });

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array<string, mixed>
     */
    private function commerceSlot(array $slot, EditorialContent $content): array
    {
        $product = $this->products->forContent($content);
        $isRsvp = $content->type === ContentType::Event
            && data_get($content->metadata, 'ticketing_mode') === 'rsvp';
        $asset = $content->relationLoaded('mediaAssets') ? $content->mediaAssets->first() : null;
        $requestedAction = (string) data_get($content->metadata, 'action_type', $isRsvp ? 'rsvp' : 'buy');
        $actionType = match (true) {
            $requestedAction === 'link' && filled(data_get($content->metadata, 'action_url')) => 'link',
            $isRsvp => 'rsvp',
            $product !== null => 'buy',
            default => 'link',
        };
        $priceLabel = $isRsvp
            ? 'FREE'
            : ($product
                ? $this->moneyLabel((int) $product['amount_cents'], (string) $product['currency'])
                : (string) ($slot['price_label'] ?? ''));
        $description = $content->summary ?: $content->body ?: ($slot['description'] ?? '');

        if ($content->type === ContentType::Event) {
            $startsAt = (string) data_get($content->metadata, 'starts_at', '');
            $timezone = (string) data_get($content->metadata, 'timezone', 'America/Panama');
            $description = collect([
                $content->summary ?: $content->body,
                data_get($content->metadata, 'location'),
                $this->eventDateLabel($startsAt, $timezone),
            ])->filter(fn (mixed $line): bool => filled($line))->implode("\n");
        }

        return [
            ...$slot,
            'content_id' => $content->id,
            'kind' => $content->type === ContentType::Event ? 'event' : (string) data_get($content->metadata, 'product_kind', 'product'),
            'title' => $content->title,
            'eyebrow' => (string) data_get($content->metadata, 'eyebrow', $slot['eyebrow'] ?? ''),
            'description' => $description,
            'price_label' => $priceLabel,
            'cta_label' => (string) data_get($content->metadata, 'cta_label', $isRsvp ? 'RSVP' : ($product ? 'BUY NOW' : 'VIEW DETAILS')),
            'countdown_at' => $content->type === ContentType::Event
                ? (string) data_get($content->metadata, 'starts_at', '')
                : (string) ($slot['countdown_at'] ?? ''),
            'action_type' => $actionType,
            'product_key' => $content->purchase_key ?: ($slot['product_key'] ?? ''),
            'url' => $actionType === 'link'
                ? (string) data_get($content->metadata, 'action_url', route('public.content.show', $content))
                : '',
            'image_url' => $asset?->publicUrl() ?? ($slot['image_url'] ?? null),
            'image_alt' => $asset?->alt_text ?: $content->title,
        ];
    }

    private function moneyLabel(int $amountCents, string $currency): string
    {
        $amount = $amountCents / 100;
        $prefix = strtoupper($currency) === 'USD' ? '$' : strtoupper($currency).' ';

        return $prefix.number_format($amount, $amountCents % 100 === 0 ? 0 : 2);
    }

    /**
     * @param  array<string, mixed>  $slot
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function configuredCommerceSlot(array $slot, array $product): array
    {
        $event = is_array($product['event'] ?? null) ? $product['event'] : null;

        return [
            ...$slot,
            'title' => (string) $product['title'],
            'description' => $event
                ? trim((string) ($event['venue'] ?? ''))."\n".$this->eventDateLabel((string) ($event['starts_at'] ?? ''), (string) ($event['timezone'] ?? 'America/Panama'))
                : $slot['description'],
            'price_label' => $this->moneyLabel((int) $product['amount_cents'], (string) $product['currency']),
            'countdown_at' => $event['starts_at'] ?? ($slot['countdown_at'] ?? ''),
            'product_key' => (string) $product['key'],
        ];
    }

    private function eventDateLabel(string $startsAt, string $timezone): string
    {
        try {
            return Carbon::parse($startsAt, $timezone)->locale('es')->translatedFormat('d/ M - g:i A');
        } catch (\Throwable) {
            return $startsAt;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        $defaults = $this->defaults();

        $royalPass = collect($defaults['royal_pass'])
            ->mapWithKeys(function (string $default, string $key) use ($payload): array {
                $value = $this->stringValue(Arr::get($payload, "royal_pass.{$key}"), $default);

                if ($key === 'cta_label') {
                    $value = $this->royalPassCtaLabel($value);
                }

                return [$key => $value];
            })
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

    private function settingsAvailable(): bool
    {
        return Schema::hasTable('site_page_settings');
    }

    private function mediaAvailable(): bool
    {
        return Schema::hasTable('media_assets')
            && Schema::hasTable('content_media_assets');
    }

    private function editorialContentAvailable(): bool
    {
        return Schema::hasTable('editorial_contents');
    }

    private function royalPassCtaLabel(string $value): string
    {
        return in_array(strtolower($value), ['buy here', 'get your royal pass'], true)
            ? $this->defaults()['royal_pass']['cta_label']
            : $value;
    }
}
