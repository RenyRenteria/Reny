<?php

use App\Models\EditorialContent;
use App\Models\SitePageSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $events = EditorialContent::query()
                ->where('type', 'event')
                ->where(function ($query): void {
                    $query->where('purchase_key', 'listening')
                        ->orWhere('slug', 'festival-de-la-rosa-dorada')
                        ->orWhere('title', 'Festival de la Rosa Dorada');
                })
                ->get();

            foreach ($events as $event) {
                if ($event->status->value === 'archived') {
                    continue;
                }

                $previousStatus = $event->status->value;
                $event->update(['status' => 'archived', 'archived_at' => now()]);
                $event->auditLogs()->create([
                    'action' => 'archived',
                    'changes' => ['source' => 'retire-rosa-dorada-festival', 'status' => [$previousStatus, 'archived']],
                    'snapshot' => ['id' => $event->id, 'type' => 'event', 'status' => 'archived'],
                ]);
            }

            SitePageSetting::query()->forSection('store', 'storefront')->get()
                ->each(function (SitePageSetting $setting) use ($events): void {
                    $payload = $setting->payload;

                    foreach ($payload['slots'] ?? [] as $key => $slot) {
                        if (! is_array($slot)) {
                            continue;
                        }

                        $linkedFestival = $events->contains('id', (int) ($slot['content_id'] ?? 0));
                        $legacyFestival = empty($slot['content_id']) && (
                            ($slot['product_key'] ?? '') === 'listening'
                            || ($slot['title'] ?? '') === 'Festival de la Rosa Dorada'
                        );

                        if ($linkedFestival || $legacyFestival) {
                            // An unavailable purchase key hides even legacy RSVP/link slots.
                            $payload['slots'][$key]['action_type'] = 'buy';
                            $payload['slots'][$key]['product_key'] = 'listening';
                        }
                    }

                    if ($payload !== $setting->payload) {
                        $setting->update(['payload' => $payload]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Keep purchase history and the CMS archive; rollback must not reopen sales.
    }
};
