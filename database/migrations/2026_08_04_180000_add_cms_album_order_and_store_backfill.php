<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('photo_albums', 'order_index')) {
            Schema::table('photo_albums', function (Blueprint $table): void {
                $table->unsignedInteger('order_index')->default(0)->index()->after('cover_photo_id');
            });
        }

        if (! Schema::hasColumn('photo_albums', 'updated_by_id')) {
            Schema::table('photo_albums', function (Blueprint $table): void {
                $table->foreignId('updated_by_id')->nullable()->after('created_by_id')->constrained('users')->nullOnDelete();
            });
        }

        DB::table('photo_albums')
            ->orderBy('id')
            ->get(['id'])
            ->values()
            ->each(fn (object $album, int $index) => DB::table('photo_albums')
                ->where('id', $album->id)
                ->update(['order_index' => $index]));

        $this->backfillFestivalDate();
    }

    public function down(): void
    {
        if (Schema::hasColumn('photo_albums', 'updated_by_id')) {
            Schema::table('photo_albums', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('updated_by_id');
            });
        }

        if (Schema::hasColumn('photo_albums', 'order_index')) {
            Schema::table('photo_albums', function (Blueprint $table): void {
                $table->dropColumn('order_index');
            });
        }
    }

    private function backfillFestivalDate(): void
    {
        $festival = DB::table('editorial_contents')
            ->where(function ($query): void {
                $query->where('purchase_key', 'listening')
                    ->orWhere('slug', 'festival-de-la-rosa-dorada')
                    ->orWhere('title', 'Festival de la Rosa Dorada');
            })
            ->orderByRaw("CASE WHEN purchase_key = 'listening' THEN 0 ELSE 1 END")
            ->first(['id', 'metadata', 'purchase_key']);

        $legacyStorefrontUsesFestival = DB::table('site_page_settings')
            ->where('page', 'store')
            ->where('section', 'storefront')
            ->get(['payload'])
            ->contains(function (object $setting): bool {
                $payload = json_decode((string) $setting->payload, true) ?: [];

                return data_get($payload, 'slots.event_secondary.product_key') === 'listening';
            });

        if (! $festival && ! $legacyStorefrontUsesFestival) {
            return;
        }

        if (! $festival) {
            $festivalId = DB::table('editorial_contents')->insertGetId([
                'type' => 'event',
                'title' => 'Festival de la Rosa Dorada',
                'slug' => 'festival-de-la-rosa-dorada',
                'summary' => 'Festival en Rock & Folk Pty, Ciudad de Panama.',
                'status' => 'published',
                'visibility' => 'open',
                'needs_approval' => false,
                'purchase_key' => 'listening',
                'published_at' => now(),
                'metadata' => json_encode($this->festivalMetadata(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('editorial_audit_logs')->insert([
                'editorial_content_id' => $festivalId,
                'actor_id' => null,
                'action' => 'created',
                'changes' => json_encode(['source' => 'issue-202-commerce-backfill']),
                'snapshot' => json_encode(['id' => $festivalId, 'type' => 'event', 'status' => 'published']),
                'created_at' => now(),
            ]);

            $festival = DB::table('editorial_contents')->where('id', $festivalId)->first([
                'id',
                'metadata',
                'purchase_key',
            ]);
        }

        $metadata = json_decode((string) $festival->metadata, true) ?: [];
        $metadata = array_replace($this->festivalMetadata(), $metadata, [
            'starts_at' => '2026-12-16 19:30:00',
            'timezone' => 'America/Panama',
        ]);

        DB::table('editorial_contents')->where('id', $festival->id)->update([
            'type' => 'event',
            'title' => 'Festival de la Rosa Dorada',
            'purchase_key' => 'listening',
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        DB::table('site_page_settings')
            ->where('page', 'store')
            ->where('section', 'storefront')
            ->get(['id', 'payload'])
            ->each(function (object $setting) use ($festival): void {
                $payload = json_decode((string) $setting->payload, true) ?: [];
                $slot = data_get($payload, 'slots.event_secondary');

                if (! is_array($slot) || ($slot['product_key'] ?? null) !== 'listening') {
                    return;
                }

                data_set($payload, 'slots.event_secondary.countdown_at', '2026-12-16 19:30:00');

                if (is_string($slot['description'] ?? null)) {
                    data_set(
                        $payload,
                        'slots.event_secondary.description',
                        preg_replace('/\b19\s*\/\s*Dic\b/i', '16/ Dic', $slot['description'])
                    );
                }

                data_set($payload, 'slots.event_secondary.content_id', (int) $festival->id);

                DB::table('site_page_settings')->where('id', $setting->id)->update([
                    'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });
    }

    /** @return array<string, mixed> */
    private function festivalMetadata(): array
    {
        return [
            'event_kind' => 'physical',
            'starts_at' => '2026-12-16 19:30:00',
            'timezone' => 'America/Panama',
            'location' => 'Rock & Folk Pty, Ciudad de Panama',
            'address' => 'Rock & Folk Pty, Ciudad de Panama',
            'ticketing_mode' => 'ticket',
            'price_cents' => 1500,
            'currency' => 'USD',
            'checkout_enabled' => true,
            'is_active' => true,
            'action_type' => 'buy',
            'cta_label' => 'GET TICKETS',
            'backfill_source' => 'issue-202-commerce-backfill',
        ];
    }
};
