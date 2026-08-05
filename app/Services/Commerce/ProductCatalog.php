<?php

namespace App\Services\Commerce;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductCatalog
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(string $productKey, ?User $user = null): ?array
    {
        $productKey = trim($productKey);

        if ($productKey === '') {
            return null;
        }

        $content = null;

        try {
            $content = $this->cmsProduct($productKey, $user);
        } catch (Throwable $exception) {
            report($exception);
        }

        if ($content instanceof EditorialContent) {
            return $this->forContent($content, $productKey);
        }

        if ($this->cmsCanonicalClaimExists($productKey)) {
            return null;
        }

        $configuredProduct = $this->configuredProduct($productKey);

        return $configuredProduct
            ? $this->normalizeConfiguredProduct($productKey, $configuredProduct)
            : null;
    }

    /**
     * @param  array<int, string>  $productKeys
     * @return Collection<int, array<string, mixed>|null>
     */
    public function findMany(array $productKeys, ?User $user = null): Collection
    {
        return collect($productKeys)
            ->map(fn (string $productKey): ?array => $this->find($productKey, $user));
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public function orderSnapshot(array $product): array
    {
        return array_filter([
            'key' => $product['key'],
            'title' => $product['title'],
            'amount_cents' => $product['amount_cents'],
            'currency' => $product['currency'] ?? 'USD',
            'kind' => $product['kind'] ?? 'product',
            'unlock_type' => $product['unlock_type'] ?? null,
            'source_type' => $product['source_type'] ?? 'order',
            'source_id' => $product['source_id'] ?? null,
            'event' => $product['event'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Normalize a canonical CMS Product/Event for both storefront and checkout.
     *
     * @return array<string, mixed>|null
     */
    public function forContent(EditorialContent $content, ?string $requestedKey = null): ?array
    {
        $amountCents = $this->amountCents($content);

        if ($amountCents === null || $amountCents <= 0 || ! $this->isAvailable($content)) {
            return null;
        }

        if ($content->type === ContentType::Event && data_get($content->metadata, 'ticketing_mode') === 'rsvp') {
            return null;
        }

        $key = trim((string) ($content->purchase_key ?: $requestedKey));
        $currency = strtoupper((string) data_get($content->metadata, 'currency', 'USD'));

        if ($key === '' || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            return null;
        }

        return [
            'key' => $key,
            'title' => $content->title,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'kind' => $this->kind($content),
            'unlock_type' => $this->unlockType($content),
            'source_type' => $this->sourceType($content),
            'source_id' => (string) $content->id,
            'image_url' => $this->contentImageUrl($content),
            'image_alt' => $this->contentImageAlt($content),
            'inventory' => is_numeric(data_get($content->metadata, 'inventory'))
                ? (int) data_get($content->metadata, 'inventory')
                : null,
            'available_from' => data_get($content->metadata, 'available_from'),
            'available_until' => data_get($content->metadata, 'available_until'),
            'event' => $this->event($content),
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function normalizeConfiguredProduct(string $key, array $product): array
    {
        return [
            'key' => $key,
            'title' => $this->stringValue($product['title']) ?? str($key)->headline()->toString(),
            'amount_cents' => (int) $product['amount_cents'],
            'currency' => strtoupper($this->stringValue($product['currency'] ?? null) ?? 'USD'),
            'kind' => $this->stringValue($product['kind']) ?? 'product',
            'unlock_type' => $this->stringValue($product['unlock_type'] ?? null),
            'source_type' => 'order',
            'event' => $this->configuredEvent($product['event'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function configuredProduct(string $productKey): ?array
    {
        $products = config('reny_catalog.products', []);
        $product = is_array($products) ? ($products[$productKey] ?? null) : null;

        if (! is_array($product)) {
            return null;
        }

        if (
            $this->stringValue($product['title'] ?? null) === null
            || ! is_numeric($product['amount_cents'] ?? null)
            || (int) $product['amount_cents'] <= 0
            || $this->stringValue($product['kind'] ?? null) === null
        ) {
            return null;
        }

        return $product;
    }

    /**
     * @return array<string, string>|null
     */
    private function configuredEvent(mixed $event): ?array
    {
        if (! is_array($event)) {
            return null;
        }

        $title = $this->stringValue($event['title'] ?? null);
        $venue = $this->stringValue($event['venue'] ?? null);
        $startsAt = $this->stringValue($event['starts_at'] ?? null);

        if ($title === null || $venue === null || $startsAt === null) {
            return null;
        }

        return [
            'title' => $title,
            'venue' => $venue,
            'address' => $this->stringValue($event['address'] ?? null) ?? $venue,
            'starts_at' => $startsAt,
            'timezone' => $this->stringValue($event['timezone'] ?? null) ?? 'America/Panama',
        ];
    }

    private function cmsProduct(string $productKey, ?User $user): ?EditorialContent
    {
        if (! $this->cmsProductsAvailable()) {
            return null;
        }

        $query = EditorialContent::query()
            ->visibleFor($user)
            ->whereIn('type', [
                ContentType::Product->value,
                ContentType::Drop->value,
                ContentType::Exclusive->value,
                ContentType::Event->value,
                ContentType::MusicalAlbum->value,
                ContentType::DeluxeAlbum->value,
            ])
            ->where(function ($query) use ($productKey): void {
                $query
                    ->where('purchase_key', $productKey)
                    ->orWhere('slug', $productKey);
            });

        if ($this->cmsMediaAvailable()) {
            $query->with(['mediaAssets']);
        }

        return $query->first();
    }

    private function cmsCanonicalClaimExists(string $productKey): bool
    {
        if (! $this->cmsProductsAvailable()) {
            return false;
        }

        return EditorialContent::query()
            ->whereIn('type', [
                ContentType::Product->value,
                ContentType::Drop->value,
                ContentType::Exclusive->value,
                ContentType::Event->value,
                ContentType::MusicalAlbum->value,
                ContentType::DeluxeAlbum->value,
            ])
            ->whereIn('status', [
                EditorialStatus::Published->value,
                EditorialStatus::Scheduled->value,
                EditorialStatus::Archived->value,
            ])
            ->where(function ($query) use ($productKey): void {
                $query->where('purchase_key', $productKey)->orWhere('slug', $productKey);
            })
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function amountCents(EditorialContent $content): ?int
    {
        $priceCents = data_get($content->metadata, 'price_cents');

        if (is_numeric($priceCents)) {
            return (int) $priceCents;
        }

        $price = data_get($content->metadata, 'price');

        if (is_numeric($price)) {
            return (int) round(((float) $price) * 100);
        }

        return null;
    }

    private function kind(EditorialContent $content): string
    {
        if ($content->type === ContentType::Event) {
            return 'ticket';
        }

        if ($content->type === ContentType::Drop) {
            return 'art_drop';
        }

        if ($content->type === ContentType::Exclusive) {
            return 'exclusive';
        }

        if (in_array($content->type, [ContentType::MusicalAlbum, ContentType::DeluxeAlbum], true)) {
            return 'digital';
        }

        return (string) data_get($content->metadata, 'product_kind', 'digital');
    }

    private function unlockType(EditorialContent $content): ?string
    {
        return match ($content->type) {
            ContentType::Drop => 'drop',
            ContentType::Exclusive => 'exclusive',
            ContentType::Product => match ((string) data_get($content->metadata, 'product_kind', 'digital')) {
                'physical' => null,
                'subscription' => null,
                'drop', 'bundle' => 'drop',
                default => 'digital',
            },
            ContentType::MusicalAlbum, ContentType::DeluxeAlbum => 'album',
            default => null,
        };
    }

    private function sourceType(EditorialContent $content): string
    {
        return match ($content->type) {
            ContentType::Product, ContentType::Drop, ContentType::Exclusive,
            ContentType::MusicalAlbum, ContentType::DeluxeAlbum => 'editorial_content',
            default => 'order',
        };
    }

    private function contentImageUrl(EditorialContent $content): ?string
    {
        return $this->contentImageAsset($content)?->publicUrl();
    }

    private function contentImageAlt(EditorialContent $content): string
    {
        $asset = $this->contentImageAsset($content);

        return (string) ($asset?->alt_text ?: $content->title);
    }

    private function contentImageAsset(EditorialContent $content): ?MediaAsset
    {
        if (! $content->relationLoaded('mediaAssets')) {
            return null;
        }

        $assetId = collect(['image_asset_id', 'cover_asset_id'])
            ->map(fn (string $key): mixed => data_get($content->metadata, $key))
            ->filter()
            ->first();

        $asset = $content->mediaAssets
            ->when($assetId, fn (Collection $assets): Collection => $assets->where('id', (int) $assetId))
            ->first();

        if (! $asset instanceof MediaAsset) {
            $asset = $content->mediaAssets->first();
        }

        return $asset;
    }

    /**
     * @return array<string, string>|null
     */
    private function event(EditorialContent $content): ?array
    {
        if ($content->type !== ContentType::Event) {
            return null;
        }

        return [
            'title' => $content->title,
            'venue' => (string) data_get($content->metadata, 'location', 'Online'),
            'address' => (string) data_get($content->metadata, 'address', data_get($content->metadata, 'location', 'Online')),
            'starts_at' => (string) data_get($content->metadata, 'starts_at', ''),
            'timezone' => (string) data_get($content->metadata, 'timezone', 'America/Panama'),
        ];
    }

    private function isAvailable(EditorialContent $content): bool
    {
        if (! filter_var(data_get($content->metadata, 'checkout_enabled', true), FILTER_VALIDATE_BOOL)
            || ! filter_var(data_get($content->metadata, 'is_active', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $inventory = data_get($content->metadata, 'inventory');

        if (is_numeric($inventory) && (int) $inventory <= 0) {
            return false;
        }

        try {
            $now = now();
            $availableFrom = data_get($content->metadata, 'availability_starts_at')
                ?? data_get($content->metadata, 'available_from')
                ?? data_get($content->metadata, 'opens_at');
            $availableUntil = data_get($content->metadata, 'availability_ends_at')
                ?? data_get($content->metadata, 'available_until')
                ?? data_get($content->metadata, 'closes_at');

            if (filled($availableFrom) && Carbon::parse((string) $availableFrom)->gt($now)) {
                return false;
            }

            if (filled($availableUntil) && Carbon::parse((string) $availableUntil)->lte($now)) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function cmsProductsAvailable(): bool
    {
        return Schema::hasTable('editorial_contents')
            && Schema::hasTable('content_release_windows')
            && Schema::hasTable('user_unlocks');
    }

    private function cmsMediaAvailable(): bool
    {
        return Schema::hasTable('media_assets')
            && Schema::hasTable('content_media_assets');
    }
}
