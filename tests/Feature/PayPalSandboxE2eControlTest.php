<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\BillingProfile;
use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\PayPalSandboxE2eControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayPalSandboxE2eControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paypal.base_url' => 'https://api-m.sandbox.paypal.com',
            'services.paypal.client_id' => 'sandbox-client-id',
            'services.paypal.webhook_id' => 'sandbox-webhook-id',
            'services.paypal.e2e.enabled' => true,
            'services.paypal.e2e.control_token' => 'sandbox-control-token',
            'services.paypal.e2e.existing_customer_email' => 'qa+paypal-existing@renyrenteria.test',
        ]);
    }

    public function test_control_routes_are_hidden_when_disabled_and_reject_wrong_tokens(): void
    {
        config(['services.paypal.e2e.enabled' => false]);

        $this->postJson('/qa/paypal-e2e/prepare', [], $this->headers())
            ->assertNotFound();

        config(['services.paypal.e2e.enabled' => true]);

        $this->postJson('/qa/paypal-e2e/prepare', [], [
            'Authorization' => 'Bearer wrong-token',
        ])->assertUnauthorized();

        config(['services.paypal.base_url' => 'https://api-m.paypal.com']);

        $this->postJson('/qa/paypal-e2e/prepare', [], $this->headers())
            ->assertNotFound();
    }

    public function test_prepare_resets_only_the_dedicated_existing_customer_fixture(): void
    {
        $fixture = User::factory()->royal()->create([
            'email' => 'qa+paypal-existing@renyrenteria.test',
            'phone' => '50760009999',
        ]);
        $other = User::factory()->royal()->create();
        $fixtureOrder = $this->completedOrder($fixture, 'PAYPAL-E2E-OLD-1-merch');
        $otherOrder = $this->completedOrder($other, 'PAYPAL-OTHER-1-merch');
        BillingProfile::create([
            'user_id' => $fixture->id,
            'provider' => 'paypal',
            'status' => 'active',
        ]);
        AccessEvent::create([
            'user_id' => $fixture->id,
            'event_name' => 'purchase',
            'resource_type' => 'order',
            'resource_key' => $fixtureOrder->provider_order_id,
        ]);

        $this->postJson('/qa/paypal-e2e/prepare', [], $this->headers())
            ->assertOk()
            ->assertExactJson([
                'status' => 'ready',
                'paypal_api_environment' => 'sandbox',
                'paypal_client_reference' => substr(hash('sha256', 'sandbox-client-id'), 0, 16),
                'paypal_webhook_reference' => substr(hash('sha256', 'sandbox-webhook-id'), 0, 16),
            ]);

        $this->assertSame('open', $fixture->fresh()->royal_status);
        $this->assertNull($fixture->fresh()->royal_ends_at);
        $this->assertNull($fixture->fresh()->phone);
        $this->assertDatabaseMissing('orders', ['id' => $fixtureOrder->id]);
        $this->assertDatabaseMissing('billing_profiles', ['user_id' => $fixture->id]);
        $this->assertDatabaseMissing('access_events', ['user_id' => $fixture->id]);

        $this->assertDatabaseHas('orders', ['id' => $otherOrder->id]);
        $this->assertTrue($other->fresh()->hasRoyalAccess());
    }

    public function test_fault_controls_are_one_shot_and_state_output_contains_only_hashed_references(): void
    {
        $user = User::factory()->create([
            'email' => 'qa+paypal-existing@renyrenteria.test',
            'royal_status' => 'open',
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-E2E-STATE-1-merch',
            'provider_capture_id' => 'CAPTURE-E2E-STATE',
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'currency' => 'USD',
            'status' => 'payment_review',
            'grants_royal_month' => true,
        ]);

        $this->postJson('/qa/paypal-e2e/arm', [], $this->headers())
            ->assertOk()
            ->assertExactJson(['status' => 'armed']);

        $control = app(PayPalSandboxE2eControl::class);
        $this->assertTrue($control->consumeBrowserPersistFailure());
        $this->assertFalse($control->consumeBrowserPersistFailure());
        $this->assertTrue($control->shouldHoldCaptureWebhook());

        $this->postJson('/qa/paypal-e2e/state', [
            'paypal_order_id' => 'PAYPAL-E2E-STATE',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('order_reference', substr(hash('sha256', 'PAYPAL-E2E-STATE'), 0, 16))
            ->assertJsonPath('capture_reference', substr(hash('sha256', 'CAPTURE-E2E-STATE'), 0, 16))
            ->assertJsonPath('order_count', 1)
            ->assertJsonPath('statuses.payment_review', 1)
            ->assertJsonMissing(['paypal_order_id' => 'PAYPAL-E2E-STATE'])
            ->assertJsonMissing(['provider_capture_id' => 'CAPTURE-E2E-STATE']);

        $this->postJson('/qa/paypal-e2e/release', [], $this->headers())
            ->assertOk()
            ->assertExactJson(['status' => 'released']);
        $this->assertFalse($control->shouldHoldCaptureWebhook());
        $this->assertSame('payment_review', $order->fresh()->status);
    }

    private function completedOrder(User $user, string $providerOrderId): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => $providerOrderId,
            'provider_capture_id' => 'CAPTURE-'.hash('sha256', $providerOrderId),
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer sandbox-control-token'];
    }
}
