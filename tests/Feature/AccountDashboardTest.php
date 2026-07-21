<?php

namespace Tests\Feature;

use App\Models\BillingProfile;
use App\Models\FanEvent;
use App\Models\Order;
use App\Models\PointLedgerEntry;
use App\Models\Rsvp;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Commerce\ProductCatalog;
use App\Services\PointLedgerService;
use App\Services\StorefrontSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AccountDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_dashboard_renders_minimal_user_hub_sections(): void
    {
        $renewalDate = now()->addMonth();
        $user = User::factory()->royal()->create([
            'name' => 'Reny Member',
            'username' => 'renyfan',
            'country_code' => 'PA',
            'locale' => 'es',
            'timezone' => 'America/Panama',
            'preferred_currency' => 'USD',
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-DASH-100-deluxe',
            'product_key' => 'deluxe',
            'amount_cents' => 2400,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => true,
            'royal_granted_until' => $user->royal_ends_at,
        ]);
        $event = FanEvent::create([
            'title' => 'Royal Listening Session',
            'venue' => 'Nexlab Stage',
            'address' => 'Panama City',
            'timezone' => 'America/Panama',
            'starts_at' => now()->addDays(5),
            'status' => 'scheduled',
            'metadata' => [
                'source' => 'paypal_checkout',
                'store_event_key' => 'listening',
            ],
        ]);

        BillingProfile::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_customer_id' => 'PAYER-DASH-100',
            'provider_subscription_id' => 'SUB-DASH-100',
            'status' => 'active',
            'payment_method_summary' => 'PayPal',
            'current_period_ends_at' => $renewalDate,
            'last_synced_at' => now(),
        ]);
        Ticket::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_code_hash' => hash('sha256', 'dash-ticket'),
            'ticket_code_preview' => 'TCKT100',
            'holder_name' => $user->name,
            'status' => 'confirmed',
            'rsvp_status' => 'confirmed',
            'purchased_at' => now(),
        ]);
        Rsvp::create([
            'event_key' => 'concert',
            'event_name' => 'Reny Renteria en Concierto',
            'name' => $user->name,
            'email' => $user->email,
            'country' => 'Panama',
        ]);
        PointLedgerEntry::create([
            'user_id' => $user->id,
            'event_type' => 'comment_posted',
            'source_type' => 'comment',
            'source_id' => 'comment-100',
            'delta' => 15,
            'status' => 'posted',
            'balance_after' => 15,
            'idempotency_key' => 'comment_posted:user-'.$user->id.':comment-100',
            'posted_at' => now(),
            'actor_type' => 'system',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('data-preferred-currency="USD"', false)
            ->assertSeeInOrder([
                'Profile',
                'Display Name',
                'Upcoming Events',
                'Registered / Purchased',
                'Royal Listening Session',
                'Reny Renteria en Concierto',
                'Available Upcoming',
                'No new events',
                'Points',
                '15 pts',
                'Purchases',
                'Deluxe Digital Album',
                'Billing',
                'Next payment date',
                $renewalDate->timezone('America/Panama')->format('F d, Y'),
                '$3.99',
                'Pause subscription',
                'Settings',
                'Language preference',
                'Currency preference',
                'Change payment method',
            ])
            ->assertDontSee('Library')
            ->assertDontSee('Manual request')
            ->assertDontSee('Account state');
    }

    public function test_account_dashboard_shows_discovery_events_and_reactivate_for_open_user(): void
    {
        $user = User::factory()->create([
            'name' => 'New Fan',
            'royal_status' => 'open',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('New Fan')
            ->assertSee('No upcoming events')
            ->assertSee('Available Upcoming')
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('Festival de la Rosa Dorada')
            ->assertSee('Available')
            ->assertSee('Buy Now')
            ->assertSee('0 pts')
            ->assertSee('No purchases yet')
            ->assertSee('Reactivate subscription');
    }

    public function test_account_dashboard_renders_when_optional_hub_tables_are_unavailable(): void
    {
        $user = User::factory()->create([
            'name' => 'Partially Migrated Fan',
            'royal_status' => 'open',
        ]);

        $unavailableTables = [
            'billing_profiles',
            'content_media_assets',
            'content_release_windows',
            'editorial_contents',
            'events',
            'media_assets',
            'orders',
            'point_ledger_entries',
            'rsvps',
            'site_page_settings',
            'tickets',
            'user_unlocks',
        ];

        Schema::partialMock()
            ->shouldReceive('hasTable')
            ->andReturnUsing(fn (string $table): bool => ! in_array($table, $unavailableTables, true));

        Schema::shouldReceive('hasColumn')->andReturn(false);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Partially Migrated Fan')
            ->assertSee('No upcoming events')
            ->assertSee('Available Upcoming')
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('0 pts')
            ->assertSee('No purchases yet')
            ->assertSee('Reactivate subscription');
    }

    public function test_account_dashboard_renders_with_invalid_user_timezone(): void
    {
        $user = User::factory()->royal()->create([
            'name' => 'Timezone Edge Fan',
            'timezone' => 'Invalid/Zone',
        ]);

        Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-TZ-100-royal',
            'product_key' => 'royal',
            'amount_cents' => 399,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => true,
            'royal_granted_until' => $user->royal_ends_at,
        ]);

        BillingProfile::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'status' => 'active',
            'payment_method_summary' => 'PayPal',
            'current_period_ends_at' => now()->addMonth(),
            'last_synced_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Timezone Edge Fan')
            ->assertSee('Pause subscription')
            ->assertSee('Royal Pass');
    }

    public function test_account_dashboard_keeps_rendering_when_dynamic_sections_fail(): void
    {
        $user = User::factory()->create([
            'name' => 'Historical Data Fan',
            'royal_status' => 'open',
        ]);

        Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-HISTORICAL-100',
            'product_key' => 'legacy-product',
            'amount_cents' => 2400,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => false,
        ]);

        $this->mock(StorefrontSettingsService::class)
            ->shouldReceive('publicPayload')
            ->once()
            ->andThrow(new RuntimeException('Malformed storefront data.'));
        $this->mock(PointLedgerService::class)
            ->shouldReceive('balance')
            ->once()
            ->andThrow(new RuntimeException('Malformed points data.'));
        $this->mock(ProductCatalog::class)
            ->shouldReceive('find')
            ->andThrow(new RuntimeException('Malformed product data.'));

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Historical Data Fan')
            ->assertSee('No upcoming events')
            ->assertSee('No new events')
            ->assertSee('0 pts')
            ->assertSee('No purchases yet')
            ->assertSee('$3.99')
            ->assertSee('Reactivate subscription');
    }

    public function test_account_dashboard_preserves_active_subscription_when_catalog_fails(): void
    {
        $renewalDate = now()->addMonth();
        $user = User::factory()->royal()->create([
            'name' => 'Active Subscriber',
        ]);

        BillingProfile::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_customer_id' => 'PAYER-ACTIVE-100',
            'provider_subscription_id' => 'SUB-ACTIVE-100',
            'status' => 'active',
            'payment_method_summary' => 'PayPal',
            'current_period_ends_at' => $renewalDate,
            'last_synced_at' => now(),
        ]);

        $this->mock(ProductCatalog::class)
            ->shouldReceive('find')
            ->andThrow(new RuntimeException('Catalog unavailable.'));

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee($renewalDate->timezone(config('admin.publishing_timezone', config('app.timezone')))->format('F d, Y'))
            ->assertSee('Pause subscription')
            ->assertDontSee('Reactivate subscription');
    }

    public function test_account_dashboard_hides_billing_actions_when_profile_loading_fails(): void
    {
        $storedUser = User::factory()->royal()->create([
            'name' => 'Unavailable Billing Fan',
        ]);

        BillingProfile::create([
            'user_id' => $storedUser->id,
            'provider' => 'paypal',
            'provider_subscription_id' => 'SUB-QA-ACTIVE-100',
            'status' => 'active',
            'current_period_ends_at' => now()->addMonth(),
        ]);

        $user = new class extends User
        {
            public function getForeignKey()
            {
                return 'user_id';
            }

            public function load($relations)
            {
                if ($relations === 'billingProfile') {
                    throw new RuntimeException('Billing database unavailable.');
                }

                return parent::load($relations);
            }
        };

        $user->setRawAttributes($storedUser->getAttributes(), true);
        $user->exists = true;
        $user->setConnection($storedUser->getConnectionName());

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertDontSee('data-account-modal-open="pauseSubscriptionModal"', false)
            ->assertDontSee('Reactivate subscription');
    }

    public function test_user_can_update_profile_preferences_and_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'name' => 'Original Name',
            'locale' => 'en',
            'preferred_currency' => 'USD',
        ]);

        $this->actingAs($user)
            ->patch(route('account.profile.update'), [
                'name' => 'Updated Name',
            ])
            ->assertRedirect();

        $this->assertSame('Updated Name', $user->fresh()->name);

        $this->actingAs($user)
            ->patch(route('account.preferences.update'), [
                'locale' => 'es',
                'preferred_currency' => 'DOP',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('es', $user->locale);
        $this->assertSame('DOP', $user->preferred_currency);

        $response = $this->actingAs($user)
            ->postJson(route('account.avatar.update'), [
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Avatar updated.');

        $avatarPath = str_replace('storage/', '', $response->json('avatar_path'));
        Storage::disk('public')->assertExists($avatarPath);
        $this->assertStringStartsWith('storage/avatars/', $user->fresh()->avatar_path);
    }

    public function test_user_can_pause_local_subscription_state_without_paypal_subscription_id(): void
    {
        $user = User::factory()->royal()->create();
        BillingProfile::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'status' => 'active',
            'payment_method_summary' => 'PayPal',
            'current_period_ends_at' => now()->addMonth(),
            'last_synced_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('account.subscription.pause'))
            ->assertRedirect();

        $profile = $user->billingProfile()->firstOrFail();
        $this->assertSame('paused', $profile->status);
        $this->assertSame('account_hub', $profile->metadata['pause_source']);
        $this->assertSame('royal_active', $user->fresh()->royal_status);
    }
}
