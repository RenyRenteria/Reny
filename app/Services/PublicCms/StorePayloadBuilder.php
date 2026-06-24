<?php

namespace App\Services\PublicCms;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\StorefrontSettingsService;

class StorePayloadBuilder
{
    public function __construct(
        private readonly ContentQuery $contentQuery,
        private readonly PayloadMediaResolver $media,
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
        ])->get();

        return [
            'storefront' => $this->storefrontSettings->publicPayload(),
            'products' => $contents
                ->whereIn('type', [ContentType::Product, ContentType::Drop, ContentType::Exclusive])
                ->values()
                ->map(fn (EditorialContent $content): array => $this->product($content))
                ->all(),
            'events' => $contents
                ->where('type', ContentType::Event)
                ->values()
                ->map(fn (EditorialContent $content): array => $this->event($content))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function product(EditorialContent $content): array
    {
        $price = ((int) $this->media->metadata($content, 'price_cents', 0)) / 100;
        $kind = (string) $this->media->metadata($content, 'product_kind', $this->media->metadata($content, 'drop_kind', 'digital'));
        $isDrop = $content->type === ContentType::Drop;
        $category = match (true) {
            $kind === 'subscription' => 'membership',
            $isDrop, $kind === 'physical', $kind === 'merch' => 'merch',
            default => 'music',
        };

        return [
            'key' => $content->purchase_key ?: $this->media->metadata($content, 'sku', $content->slug),
            'name' => $content->title,
            'type' => $isDrop ? 'Art Drop' : str($kind)->headline()->toString(),
            'category' => $category,
            'price' => $price,
            'suffix' => $kind === 'subscription' ? '/mo' : '',
            'availability' => $this->media->availability($content),
            'points' => '+0 pts',
            'pass' => $content->visibility->value === 'open' ? 'No Royal Pass required' : 'Access-gated',
            'access' => $content->purchase_key ? 'Unlocks in profile' : 'Public checkout',
            'image' => $this->media->metadata($content, 'fallback_image', 'cover.jpg'),
            'image_url' => $this->media->mediaUrl($content, ['image_asset_id', 'cover_asset_id']),
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
    private function event(EditorialContent $content): array
    {
        $startsAt = $this->media->metadata($content, 'starts_at');
        $price = ((int) $this->media->metadata($content, 'price_cents', 0)) / 100;

        return [
            'key' => $content->purchase_key ?: $this->media->metadata($content, 'sku', $content->slug),
            'name' => $content->title,
            'kicker' => str((string) $this->media->metadata($content, 'event_kind', 'event'))->headline()->toString(),
            'date' => $startsAt ? date('M d, Y', strtotime((string) $startsAt)) : 'Date TBA',
            'place' => $this->media->metadata($content, 'location', 'Online'),
            'price' => $price,
            'image' => $this->media->metadata($content, 'fallback_image', 'reny-store-concert-poster.png'),
            'image_url' => $this->media->mediaUrl($content, ['image_asset_id', 'cover_asset_id']),
            'action' => $this->media->metadata($content, 'ticketing_mode') === 'rsvp' ? 'RSVP' : 'Buy ticket',
            'mode' => $this->media->metadata($content, 'ticketing_mode') === 'rsvp' ? 'rsvp' : 'buy',
        ];
    }
}
