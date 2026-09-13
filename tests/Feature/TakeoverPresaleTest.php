<?php

namespace Tests\Feature;

use App\Enums\EditorialStatus;
use App\Models\EditorialContent;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Commerce\ProductCatalog;
use App\Services\Commerce\TicketInventory;
use App\Services\PayPalService;
use App\Services\TakeoverPresaleSeeder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class TakeoverPresaleTest extends TestCase
{
    use RefreshDatabase;

    private EditorialContent $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 12)->startOfDay());
        $this->event = app(TakeoverPresaleSeeder::class)->seed();
    }

    public function test_shows_and_checkout_use_the_approved_poster_price_and_times_without_stock_counts(): void
    {
        foreach (['/shows', '/store', '/store/checkout/'.TakeoverPresaleSeeder::KEY] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Reny Renteria Takeover')
                ->assertSee('Presale [Limited]')
                ->assertSee('$20')
                ->assertSee('Puertas 7:30 PM')
                ->assertSee('Show 8:30 PM')
                ->assertSee('Rock &amp; Folk Pty', false)
                ->assertSee(TakeoverPresaleSeeder::POSTER)
                ->assertDontSee('400 available')
                ->assertDontSee('inventory');
        }

        $html = $this->get('/shows')->getContent();
        $this->assertSame(1, substr_count($html, '<h2>Reny Renteria Takeover</h2>'));
        $this->assertStringContainsString('data-countdown-at="2027-10-02T20:30:00-05:00"', $html);
        $this->assertFileExists(public_path(TakeoverPresaleSeeder::POSTER));
        $payload = $this->getJson('/api/public-content/store')->assertOk()->json();
        $event = collect($payload['events'])->firstWhere('key', TakeoverPresaleSeeder::KEY);
        $this->assertSame(2000, $event['amount_cents']);
        $this->assertSame('Oct 02, 2027', $event['date']);
        $this->assertArrayNotHasKey('inventory', $event);
        $this->assertArrayNotHasKey('inventory_tracking', $event);
    }

    public function test_reseeding_preserves_existing_event_edits_and_inventory(): void
    {
        $this->event->update([
            'title' => 'Updated event',
            'status' => EditorialStatus::Archived,
            'metadata' => [...$this->event->metadata, 'inventory' => 250],
        ]);

        $again = app(TakeoverPresaleSeeder::class)->seed();
        $this->assertSame($this->event->id, $again->id);
        $this->assertSame('Updated event', $again->title);
        $this->assertSame(EditorialStatus::Archived, $again->status);
        $this->assertSame(250, $again->metadata['inventory']);
        $this->assertNull(app(ProductCatalog::class)->find(TakeoverPresaleSeeder::KEY));
    }

    public function test_only_the_last_of_400_tickets_can_be_reserved_and_oversized_bags_are_rejected(): void
    {
        $this->sellTickets(399);
        $this->mock(PayPalService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createOrder')->once()->with(2000, 'USD', [[
                'name' => 'Reny Renteria Takeover', 'unit_amount_cents' => 2000, 'quantity' => 1,
            ]])->andReturn(['order_id' => 'LAST-TICKET']);
        });

        $this->postJson('/checkout/paypal/orders', $this->checkoutData(2))
            ->assertUnprocessable()->assertJsonValidationErrors('product_keys');
        $this->assertDatabaseCount('orders', 399);
        $this->postJson('/checkout/paypal/orders', $this->checkoutData())->assertOk();
        $this->assertDatabaseCount('orders', 400);
        $this->postJson('/checkout/paypal/orders', $this->checkoutData())
            ->assertUnprocessable()->assertJsonValidationErrors('product_keys');
        $this->assertDatabaseCount('orders', 400);
        $this->get('/store/checkout/'.TakeoverPresaleSeeder::KEY)->assertNotFound();
    }

    public function test_canceling_an_unpaid_checkout_releases_its_tickets(): void
    {
        $this->setCapacity(1);
        $this->mockCreatedOrder('CANCEL-TICKET');
        $this->postJson('/checkout/paypal/orders', $this->checkoutData())->assertOk();
        $this->assertSame(0, app(TicketInventory::class)->remaining($this->event));
        $this->postJson('/checkout/paypal/orders/cancel', ['paypal_order_id' => 'CANCEL-TICKET'])->assertOk();
        $this->assertSame(1, app(TicketInventory::class)->remaining($this->event));
    }

    public function test_reservation_rechecks_stock_even_when_a_catalog_snapshot_is_stale(): void
    {
        $product = app(ProductCatalog::class)->find(TakeoverPresaleSeeder::KEY);
        $this->sellTickets(400);
        $this->expectException(ValidationException::class);

        DB::transaction(fn () => app(TicketInventory::class)->reserve(collect([$product])));
    }

    public function test_payment_review_holds_capacity_without_expiring(): void
    {
        $this->sellTickets(400);
        Order::query()->first()->update(['status' => 'payment_review']);
        $this->travel(31)->minutes();
        $this->assertNull(app(ProductCatalog::class)->find(TakeoverPresaleSeeder::KEY));
    }

    public function test_paypal_creation_failure_releases_stock(): void
    {
        $this->setCapacity(1);
        $this->mock(PayPalService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createOrder')->once()->andThrow(ValidationException::withMessages(['paypal' => 'Unavailable']));
        });
        $this->postJson('/checkout/paypal/orders', $this->checkoutData())->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['status' => 'failed', 'product_key' => TakeoverPresaleSeeder::KEY]);
        $this->assertSame(1, app(TicketInventory::class)->remaining($this->event));
    }

    public function test_abandoned_reservations_expire_and_cannot_be_captured_after_stock_is_reallocated(): void
    {
        $this->setCapacity(1);
        $this->mock(PayPalService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createOrder')->twice()->andReturn(['order_id' => 'EXPIRED-TICKET'], ['order_id' => 'NEW-TICKET']);
            $mock->shouldNotReceive('captureOrder');
        });
        $this->postJson('/checkout/paypal/orders', $this->checkoutData())->assertOk();
        $this->travel(31)->minutes();
        $this->assertSame(1, app(TicketInventory::class)->remaining($this->event));
        $this->postJson('/checkout/paypal/orders', $this->checkoutData())->assertOk();
        $this->postJson('/checkout/paypal', [...$this->checkoutData(), 'paypal_order_id' => 'EXPIRED-TICKET'])
            ->assertUnprocessable()->assertJsonValidationErrors('paypal_order_id');
        $this->assertSame(0, app(TicketInventory::class)->remaining($this->event));
    }

    public function test_capture_pins_stock_and_delivers_the_correct_event_ticket(): void
    {
        $this->setCapacity(1);
        $this->mockCreatedOrder('PAID-TICKET');
        $this->postJson('/checkout/paypal/orders', $this->checkoutData())->assertOk();
        $this->mock(PayPalService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('captureOrder')->once()->with('PAID-TICKET', 2000, 'USD')
                ->andReturnUsing(function (): array {
                    $this->travel(31)->minutes();
                    $this->assertSame(0, app(TicketInventory::class)->remaining($this->event));
                    $this->postJson('/checkout/paypal/orders/cancel', ['paypal_order_id' => 'PAID-TICKET'])
                        ->assertUnprocessable();

                    return ['order_id' => 'PAID-TICKET', 'capture_id' => 'CAPTURE-TICKET', 'payer_id' => null, 'payload' => []];
                });
        });

        $this->postJson('/checkout/paypal', [...$this->checkoutData(), 'paypal_order_id' => 'PAID-TICKET'])
            ->assertOk()->assertJsonPath('status', 'completed');
        $ticket = Ticket::query()->with('event')->sole();
        $this->assertSame('Reny Renteria Takeover', $ticket->event->title);
        $this->assertSame('Rock & Folk Pty', $ticket->event->venue);
        $this->assertSame('2027-10-02 20:30', $ticket->event->starts_at->setTimezone('America/Panama')->format('Y-m-d H:i'));
        $this->assertSame(0, app(TicketInventory::class)->remaining($this->event));
        $this->get('/account')->assertOk()->assertSee(TakeoverPresaleSeeder::POSTER)->assertSee('8:30 PM');
    }

    public function test_uncertain_capture_keeps_stock_reserved_and_cannot_be_canceled(): void
    {
        $this->setCapacity(1);
        $this->mockCreatedOrder('UNCERTAIN-TICKET');
        $this->postJson('/checkout/paypal/orders', $this->checkoutData())->assertOk();
        $this->mock(PayPalService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('captureOrder')->once()->andThrow(ValidationException::withMessages(['paypal' => 'Confirmation pending']));
        });
        $this->postJson('/checkout/paypal', [...$this->checkoutData(), 'paypal_order_id' => 'UNCERTAIN-TICKET'])->assertUnprocessable();
        $this->travel(31)->minutes();
        $this->assertSame(0, app(TicketInventory::class)->remaining($this->event));
        $this->postJson('/checkout/paypal/orders/cancel', ['paypal_order_id' => 'UNCERTAIN-TICKET'])->assertUnprocessable();
    }

    public function test_local_payment_references_hold_stock_until_manual_resolution(): void
    {
        $this->setCapacity(1);
        $this->actingAs(User::factory()->create());
        $this->postJson('/checkout/local', [...$this->checkoutData(), 'local_reference' => 'TAKEOVER-1234'])->assertOk();
        $this->travel(31)->minutes();
        $this->postJson('/checkout/local', [...$this->checkoutData(), 'local_reference' => 'TAKEOVER-5678'])
            ->assertUnprocessable()->assertJsonValidationErrors('product_keys');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_presale_checkout_closes_when_the_show_starts(): void
    {
        $this->travelTo(Carbon::parse('2027-10-02T20:30:00-05:00'));
        $this->assertNull(app(ProductCatalog::class)->find(TakeoverPresaleSeeder::KEY));
    }

    private function checkoutData(int $quantity = 1): array
    {
        return [
            'identifier' => 'takeover-fan@example.test',
            'customer_name' => 'Takeover Fan',
            'customer_email' => 'takeover-fan@example.test',
            'customer_country' => 'Panama',
            'product_keys' => array_fill(0, $quantity, TakeoverPresaleSeeder::KEY),
            'currency' => 'USD',
        ];
    }

    private function mockCreatedOrder(string $id): void
    {
        $this->mock(PayPalService::class, function (MockInterface $mock) use ($id): void {
            $mock->shouldReceive('createOrder')->once()->andReturn(['order_id' => $id]);
        });
    }

    private function setCapacity(int $quantity): void
    {
        $this->event->update(['metadata' => [...$this->event->metadata, 'inventory' => $quantity]]);
    }

    private function sellTickets(int $quantity): void
    {
        $user = User::factory()->create();
        DB::table('orders')->insert(array_map(fn (int $index): array => [
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'SOLD-'.$index,
            'product_key' => TakeoverPresaleSeeder::KEY,
            'amount_cents' => 2000,
            'currency' => 'USD',
            'status' => 'completed',
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], range(1, $quantity)));
    }
}
