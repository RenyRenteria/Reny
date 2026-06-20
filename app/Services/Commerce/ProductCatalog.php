<?php

namespace App\Services\Commerce;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\User;
use Illuminate\Support\Collection;

class ProductCatalog
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const STATIC_PRODUCTS = [
        'deluxe' => [
            'title' => 'Deluxe Digital Album',
            'amount_cents' => 2400,
            'kind' => 'digital',
            'unlock_type' => 'album',
        ],
        'singles' => [
            'title' => 'Singles / Digital Pack',
            'amount_cents' => 800,
            'kind' => 'digital',
            'unlock_type' => 'album',
        ],
        'royal' => [
            'title' => 'Royal Pass',
            'amount_cents' => 499,
            'kind' => 'subscription',
            'unlock_type' => null,
        ],
        'merch' => [
            'title' => 'Signature Merch',
            'amount_cents' => 4800,
            'kind' => 'merch',
            'unlock_type' => null,
        ],
        'print' => [
            'title' => 'Numbered Art Print',
            'amount_cents' => 8600,
            'kind' => 'art_drop',
            'unlock_type' => 'drop',
        ],
        'concert' => [
            'title' => 'Reny Live - Studio Night',
            'amount_cents' => 4200,
            'kind' => 'ticket',
            'unlock_type' => null,
            'event' => [
                'title' => 'Reny Live - Studio Night',
                'venue' => 'Panama City',
                'address' => 'Panama City',
                'starts_at' => '2026-08-24 20:00:00',
                'timezone' => 'America/Panama',
            ],
        ],
        'listening' => [
            'title' => 'Festival de la Rosa Dorada',
            'amount_cents' => 1500,
            'kind' => 'ticket',
            'unlock_type' => null,
            'event' => [
                'title' => 'Festival de la Rosa Dorada',
                'venue' => 'Rock & Folk Pty, Ciudad de Panama',
                'address' => 'Rock & Folk Pty, Ciudad de Panama',
                'starts_at' => '2026-12-19 19:30:00',
                'timezone' => 'America/Panama',
            ],
        ],
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $productKey, ?User $user = null): ?array
    {
        $productKey = trim($productKey);

        if (isset(self::STATIC_PRODUCTS[$productKey])) {
            return $this->normalizeStaticProduct($productKey, self::STATIC_PRODUCTS[$productKey]);
        }

        $content = EditorialContent::query()
            ->visibleFor($user)
            ->whereIn('type', [
                ContentType::Product->value,
                ContentType::Drop->value,
                ContentType::Exclusive->value,
                ContentType::Event->value,
            ])
            ->where(function ($query) use ($productKey): void {
                $query
                    ->where('purchase_key', $productKey)
                    ->orWhere('slug', $productKey);
            })
            ->first();

        if (! $content) {
            return null;
        }

        return $this->normalizeCmsProduct($content, $productKey);
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
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function normalizeStaticProduct(string $key, array $product): array
    {
        return [
            'key' => $key,
            'title' => $product['title'],
            'amount_cents' => $product['amount_cents'],
            'currency' => 'USD',
            'kind' => $product['kind'],
            'unlock_type' => $product['unlock_type'] ?? null,
            'source_type' => 'order',
            'event' => $product['event'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeCmsProduct(EditorialContent $content, string $requestedKey): ?array
    {
        $amountCents = $this->amountCents($content);

        if ($amountCents === null || $amountCents <= 0) {
            return null;
        }

        if ($content->type === ContentType::Event && data_get($content->metadata, 'ticketing_mode') === 'rsvp') {
            return null;
        }

        $key = $content->purchase_key ?: $requestedKey;

        return [
            'key' => $key,
            'title' => $content->title,
            'amount_cents' => $amountCents,
            'currency' => strtoupper((string) data_get($content->metadata, 'currency', 'USD')),
            'kind' => $this->kind($content),
            'unlock_type' => $this->unlockType($content),
            'source_type' => $this->sourceType($content),
            'source_id' => (string) $content->id,
            'event' => $this->event($content),
        ];
    }

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
            default => null,
        };
    }

    private function sourceType(EditorialContent $content): string
    {
        return match ($content->type) {
            ContentType::Product, ContentType::Drop, ContentType::Exclusive => 'editorial_content',
            default => 'order',
        };
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
            'starts_at' => (string) data_get($content->metadata, 'starts_at', now()->addMonth()->toDateTimeString()),
            'timezone' => (string) data_get($content->metadata, 'timezone', 'America/Panama'),
        ];
    }
}
