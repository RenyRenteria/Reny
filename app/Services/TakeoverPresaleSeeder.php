<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;

class TakeoverPresaleSeeder
{
    public const KEY = 'reny-renteria-takeover-2027';

    public const POSTER = 'images/store/reny-takeover-2027.png';

    public function seed(): EditorialContent
    {
        // A later deploy must never reset sales, republish, or overwrite CMS edits.
        return EditorialContent::query()->firstOrCreate([
            'type' => ContentType::Event->value,
            'slug' => self::KEY,
        ], [
            'purchase_key' => self::KEY,
            'title' => 'Reny Renteria Takeover',
            'summary' => "Presale [Limited]\nPuertas 7:30 PM · Show 8:30 PM",
            'status' => EditorialStatus::Published->value,
            'visibility' => VisibilityAudience::Open->value,
            'needs_approval' => false,
            'published_at' => now(),
            'metadata' => [
                'event_kind' => 'concert',
                'eyebrow' => 'Presale [Limited]',
                'location' => 'Rock & Folk Pty',
                'starts_at' => '2027-10-02T20:30:00-05:00',
                'doors_at' => '2027-10-02T19:30:00-05:00',
                'timezone' => 'America/Panama',
                'ticketing_mode' => 'paid',
                'action_type' => 'buy',
                'cta_label' => 'GET TICKETS',
                'price_cents' => 2000,
                'currency' => 'USD',
                'inventory' => 400,
                'inventory_tracking' => 'orders',
                'hide_inventory' => true,
                'checkout_enabled' => true,
                'is_active' => true,
                'availability_ends_at' => '2027-10-02T20:30:00-05:00',
                'fallback_image' => self::POSTER,
            ],
        ]);
    }
}
