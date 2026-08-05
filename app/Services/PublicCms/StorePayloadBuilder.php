<?php

namespace App\Services\PublicCms;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\Commerce\ProductCatalog;
use App\Services\StorefrontSettingsService;

class StorePayloadBuilder
{
    public function __construct(
        private readonly ContentQuery $contentQuery,
        private readonly PayloadMediaResolver $media,
        private readonly ProductCatalog $products,
        private readonly StorefrontSettingsService $storefrontSettings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?User $user): array
    {
        $contents = $this->contentQuery->visibleContents($user, [
            ContentType::Product,
            ContentType::Event,
            ContentType::Drop,
            ContentType::Exclusive,
        ], null)->get();

        return [
            'storefront' => $this->storefrontSettings->publicPayload($user),
            'products' => $contents
                ->whereIn('type', [
                    ContentType::Product,
                    ContentType::Drop,
                    ContentType::Exclusive,
                ])
                ->values()
                ->map(fn (EditorialContent $content): ?array => $this->product($content))
                ->filter()
                ->values()
                ->all(),
            'events' => $contents
                ->where('type', ContentType::Event)
                ->values()
                ->map(fn (EditorialContent $content): ?array => $this->event($content))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function product(EditorialContent $content): ?array
    {
        $actionType = (string) $this->media->metadata($content, 'action_type', 'buy');
        $actionUrl = trim((string) $this->media->metadata($content, 'action_url', ''));
        $product = $actionType === 'buy' ? $this->products->forContent($content) : null;

        if (($actionType === 'buy' && ! is_array($product))
            || ($actionType === 'link' && $actionUrl === '')) {
            return null;
        }

        $amountCents = is_array($product) ? (int) $product['amount_cents'] : 0;
        $currency = is_array($product) ? (string) $product['currency'] : 'USD';
        $price = $amountCents / 100;
        $kind = (string) $this->media->metadata($content, 'product_kind', $this->media->metadata($content, 'drop_kind', 'digital'));
        $isDrop = $content->type === ContentType::Drop;
        $category = match (true) {
            $kind === 'subscription' => 'membership',
            $isDrop, $kind === 'physical', $kind === 'merch' => 'merch',
            default => 'music',
        };

        return [
            'content_id' => $content->id,
            'key' => $content->purchase_key ?: $this->media->metadata($content, 'sku', $content->slug),
            'name' => $content->title,
            'type' => $isDrop ? 'Art Drop' : str($kind)->headline()->toString(),
            'category' => $category,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'price' => $price,
            'suffix' => $kind === 'subscription' ? '/mo' : '',
            'availability' => $this->media->availability($content),
            'points' => '+0 pts',
            'pass' => $content->visibility->value === 'open' ? 'No Royal Pass required' : 'Access-gated',
            'access' => $content->purchase_key ? 'Unlocks in profile' : 'Public checkout',
            'image' => $this->media->metadata($content, 'fallback_image', 'cover.jpg'),
            'image_url' => $this->media->mediaUrl($content, ['image_asset_id', 'cover_asset_id']),
            'summary' => $content->summary ?: $content->body ?: '',
            'cta' => (string) $this->media->metadata($content, 'cta_label', match ($category) {
                'membership' => 'Join membership',
                'music' => 'Buy music',
                default => 'Add to bag',
            }),
            'mode' => $actionType,
            'action_url' => $actionType === 'link' ? $actionUrl : null,
            'checkout_url' => is_array($product) ? route('store.checkout', ['product' => $product['key']]) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function event(EditorialContent $content): ?array
    {
        $startsAt = $this->media->metadata($content, 'starts_at');
        $isRsvp = $this->media->metadata($content, 'ticketing_mode') === 'rsvp';
        $actionType = (string) $this->media->metadata($content, 'action_type', $isRsvp ? 'rsvp' : 'buy');
        $actionUrl = trim((string) $this->media->metadata($content, 'action_url', ''));
        $product = $actionType === 'buy' ? $this->products->forContent($content) : null;

        if (($actionType === 'buy' && ! is_array($product))
            || ($actionType === 'link' && $actionUrl === '')) {
            return null;
        }

        $amountCents = is_array($product) ? (int) $product['amount_cents'] : 0;
        $currency = is_array($product) ? (string) $product['currency'] : 'USD';
        $key = $content->purchase_key ?: $this->media->metadata($content, 'sku', $content->slug);

        return [
            'content_id' => $content->id,
            'key' => $key,
            'name' => $content->title,
            'kicker' => str((string) $this->media->metadata($content, 'event_kind', 'event'))->headline()->toString(),
            'date' => $startsAt ? date('M d, Y', strtotime((string) $startsAt)) : 'Date TBA',
            'place' => $this->media->metadata($content, 'location', 'Online'),
            'starts_at' => $startsAt,
            'timezone' => $this->media->metadata($content, 'timezone', config('admin.publishing_timezone', 'America/Panama')),
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'price' => $amountCents / 100,
            'image' => $this->media->metadata($content, 'fallback_image', 'reny-store-concert-poster.png'),
            'image_url' => $this->media->mediaUrl($content, ['image_asset_id', 'cover_asset_id']),
            'summary' => $content->summary ?: $content->body ?: '',
            'action' => (string) $this->media->metadata($content, 'cta_label', $actionType === 'rsvp' ? 'RSVP' : ($actionType === 'link' ? 'View details' : 'Buy ticket')),
            'mode' => $actionType,
            'action_url' => $actionType === 'link' ? $actionUrl : null,
            'checkout_url' => $actionType === 'buy' ? route('store.checkout', ['product' => $key]) : null,
        ];
    }
}
