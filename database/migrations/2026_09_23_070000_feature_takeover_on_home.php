<?php

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\SitePageSetting;
use App\Services\StorefrontSettingsService;
use App\Services\TakeoverPresaleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $event = EditorialContent::query()
                ->where('type', ContentType::Event->value)
                ->where('slug', TakeoverPresaleSeeder::KEY)
                ->first();

            if (! $event) {
                return;
            }

            $setting = SitePageSetting::query()->firstOrNew([
                'page' => StorefrontSettingsService::PAGE,
                'section' => StorefrontSettingsService::SECTION,
                'status' => SitePageSetting::STATUS_PUBLISHED,
            ]);
            $payload = $setting->payload ?? [];

            // Link the existing event so CMS visibility, pricing and checkout remain authoritative.
            // Clear the previous show's overrides, including its poster and free RSVP price.
            $payload['slots']['event_primary'] = [
                'content_id' => $event->id,
                'product_key' => $event->purchase_key,
                'title' => '',
                'eyebrow' => '',
                'description' => '',
                'price_label' => '',
                'action_type' => 'buy',
                'url' => '',
                'countdown_at' => '',
                'image' => data_get($event->metadata, 'fallback_image', TakeoverPresaleSeeder::POSTER),
                'image_asset_id' => null,
                'image_alt' => $event->title,
            ];

            $setting->fill(['payload' => $payload]);

            if (! $setting->exists) {
                $setting->published_at = now();
            }

            if ($setting->isDirty()) {
                $setting->save();
            }
        });
    }

    public function down(): void
    {
        // Keep the editorial selection; rollback must not restore the expired show.
    }
};
