<?php

namespace Tests\Feature;

use App\Models\BillingProfile;
use App\Models\FanEvent;
use App\Models\Order;
use App\Models\PointLedgerEntry;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserUnlock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserHubDataFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_hub_tables_exist_with_required_foundation_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'username',
            'avatar_path',
            'country_code',
            'locale',
            'timezone',
            'preferred_currency',
            'bio',
        ]));

        $this->assertTrue(Schema::hasColumns('point_ledger_entries', [
            'event_type',
            'source_type',
            'source_id',
            'delta',
            'status',
            'balance_after',
            'idempotency_key',
            'actor_type',
            'actor_id',
            'reason',
            'metadata',
        ]));

        $this->assertTrue(Schema::hasColumns('tickets', [
            'ticket_code_hash',
            'ticket_code_preview',
            'holder_name',
            'status',
            'rsvp_status',
            'checked_in_at',
        ]));
    }

    public function test_user_keeps_purchased_unlocks_when_royal_pass_expires(): void
    {
        $user = User::factory()->expiredRoyal()->create([
            'username' => 'expiredfan',
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-UNLOCK-100-deluxe',
            'product_key' => 'deluxe',
            'amount_cents' => 2400,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => true,
            'royal_granted_until' => now()->subDay(),
        ]);

        UserUnlock::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'unlock_type' => 'album',
            'product_key' => 'deluxe',
            'title' => 'Deluxe Album',
            'source_type' => 'order',
            'source_id' => $order->provider_order_id,
            'status' => 'available',
            'unlocked_at' => now()->subWeek(),
        ]);

        $this->assertFalse($user->fresh()->hasRoyalAccess());
        $this->assertSame('Deluxe Album', $user->unlocks()->available()->first()?->title);
    }

    public function test_billing_profile_tracks_backend_provider_state(): void
    {
        $user = User::factory()->create();

        BillingProfile::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_customer_id' => 'PAYER-100',
            'provider_subscription_id' => 'SUB-100',
            'status' => 'active',
            'payment_method_summary' => 'PayPal',
            'current_period_ends_at' => now()->addMonth(),
            'last_synced_at' => now(),
        ]);

        $profile = $user->billingProfile()->firstOrFail();

        $this->assertSame('paypal', $profile->provider);
        $this->assertSame('active', $profile->status);
        $this->assertNotNull($profile->last_synced_at);
    }

    public function test_points_ledger_rejects_duplicate_idempotency_key(): void
    {
        $user = User::factory()->create();
        $key = 'video_watched:user-'.$user->id.':behind-scenes-1';

        PointLedgerEntry::create([
            'user_id' => $user->id,
            'event_type' => 'video_watched',
            'source_type' => 'video',
            'source_id' => 'behind-scenes-1',
            'delta' => 5,
            'status' => 'posted',
            'balance_after' => 5,
            'idempotency_key' => $key,
            'posted_at' => now(),
            'actor_type' => 'system',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        PointLedgerEntry::create([
            'user_id' => $user->id,
            'event_type' => 'video_watched',
            'source_type' => 'video',
            'source_id' => 'behind-scenes-1',
            'delta' => 5,
            'status' => 'posted',
            'balance_after' => 10,
            'idempotency_key' => $key,
            'posted_at' => now(),
            'actor_type' => 'system',
        ]);
    }

    public function test_ticket_belongs_to_internal_event_and_stores_only_code_hash(): void
    {
        $user = User::factory()->create(['name' => 'Ticket Holder']);
        $event = FanEvent::create([
            'title' => 'Royal Listening Session',
            'venue' => 'Nexlab Stage',
            'address' => 'Panama City',
            'timezone' => 'America/Panama',
            'starts_at' => now()->addWeek(),
            'status' => 'scheduled',
        ]);
        $rawCode = 'ticket-'.$user->id.'-'.Str::random(24);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'ticket_code_hash' => hash('sha256', $rawCode),
            'ticket_code_preview' => substr($rawCode, -8),
            'holder_name' => $user->name,
            'status' => 'confirmed',
            'rsvp_status' => 'confirmed',
            'purchased_at' => now(),
        ]);

        $this->assertSame('Royal Listening Session', $ticket->event->title);
        $this->assertStringNotContainsString($user->email, $ticket->ticket_code_hash);
        $this->assertNotSame($rawCode, $ticket->ticket_code_hash);
    }
}
