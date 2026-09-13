<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\EditorialContent;
use App\Models\Order;
use App\Models\SitePageSetting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Commerce\ProductCatalog;
use App\Services\TakeoverPresaleSeeder;
use App\Services\UserHubPurchaseSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetireRosaDoradaFestivalTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_catalog_has_no_festival_or_checkout_fallback(): void
    {
        app(TakeoverPresaleSeeder::class)->seed();

        $this->assertFestivalUnavailable();
        $this->get('/shows')->assertOk()->assertSee('Reny Renteria Takeover')->assertSee('$20');
        $this->get('/store/checkout/'.TakeoverPresaleSeeder::KEY)->assertOk()->assertSee('Presale [Limited]');
    }

    public function test_migration_archives_festival_and_hides_linked_and_legacy_slots_without_touching_purchases(): void
    {
        $takeover = app(TakeoverPresaleSeeder::class)->seed();
        $festival = EditorialContent::factory()->published()->create([
            'type' => ContentType::Event,
            'title' => 'Festival de la Rosa Dorada',
            'slug' => 'festival-de-la-rosa-dorada',
            'purchase_key' => 'listening',
            'visibility' => 'open',
            'metadata' => [
                'starts_at' => '2026-12-16 19:30:00',
                'timezone' => 'America/Panama',
                'location' => 'Rock & Folk Pty',
                'price_cents' => 1500,
                'ticketing_mode' => 'paid',
            ],
        ]);
        $video = EditorialContent::factory()->published()->create([
            'type' => ContentType::Video, 'title' => 'Festival de la Rosa Dorada',
        ]);
        foreach (['published', 'draft'] as $status) {
            SitePageSetting::create([
                'page' => 'store', 'section' => 'storefront', 'status' => $status,
                'payload' => ['slots' => [
                    'event_primary' => ['title' => $festival->title, 'product_key' => 'listening', 'action_type' => 'rsvp'],
                    'event_secondary' => ['content_id' => $festival->id, 'action_type' => 'link', 'url' => '/old-festival'],
                    'merch' => ['product_key' => 'merch', 'title' => 'Keep merchandise'],
                ]],
            ]);
        }

        $user = User::factory()->create();
        $product = app(ProductCatalog::class)->find('listening');
        $order = Order::create([
            'user_id' => $user->id, 'provider' => 'paypal', 'provider_order_id' => 'FESTIVAL-PAID',
            'product_key' => 'listening', 'amount_cents' => 1500, 'currency' => 'USD',
            'status' => 'completed', 'metadata' => ['product' => app(ProductCatalog::class)->orderSnapshot($product)],
        ]);
        app(UserHubPurchaseSync::class)->recordCompletedOrder($user, $order, []);
        $ticket = Ticket::query()->sole();
        $orderBefore = $order->fresh()->getAttributes();
        $ticketBefore = $ticket->getAttributes();
        $eventBefore = $ticket->event->getAttributes();
        $takeoverBefore = $takeover->fresh()->getAttributes();
        $this->get('/shows')->assertOk()->assertSee($festival->title);

        $migration = require database_path('migrations/2026_09_13_010000_retire_rosa_dorada_festival.php');
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertFestivalUnavailable();
        $this->assertSame(EditorialStatus::Archived, $festival->fresh()->status);
        $this->assertSame(1, $festival->auditLogs()->count());
        $this->assertSame(EditorialStatus::Published, $video->fresh()->status);
        $this->assertSame($orderBefore, $order->fresh()->getAttributes());
        $this->assertSame($ticketBefore, $ticket->fresh()->getAttributes());
        $this->assertSame($eventBefore, $ticket->event->fresh()->getAttributes());
        $this->assertSame($takeoverBefore, $takeover->fresh()->getAttributes());
        foreach (SitePageSetting::all() as $setting) {
            $this->assertSame('buy', data_get($setting->payload, 'slots.event_primary.action_type'));
            $this->assertSame('Keep merchandise', data_get($setting->payload, 'slots.merch.title'));
        }
        $this->get('/shows')->assertOk()->assertSee('Reny Renteria Takeover')->assertSee('$20');
        $this->actingAs($user)->get('/account')->assertOk()->assertSee('Festival de la Rosa Dorada');
    }

    private function assertFestivalUnavailable(): void
    {
        foreach (['/', '/shows', '/store', '/api/public-content/store', '/api/public-content/home'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('Festival de la Rosa Dorada')->assertDontSee('rosa-dorada.png');
        }
        foreach (['listening', 'festival-de-la-rosa-dorada'] as $key) {
            $this->assertNull(app(ProductCatalog::class)->find($key));
            $this->get('/store/checkout/'.$key)->assertNotFound();
        }
        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'fan@example.com', 'customer_name' => 'Test Fan', 'customer_email' => 'fan@example.com',
            'customer_country' => 'Panama', 'product_keys' => ['listening'], 'currency' => 'USD',
        ])->assertUnprocessable()->assertJsonValidationErrors('product_keys');
    }
}
