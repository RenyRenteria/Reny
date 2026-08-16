<?php

namespace Tests\Feature;

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
            'services.paypal.e2e.reference_key' => 'sandbox-reference-key',
            'services.paypal.e2e.release_sha' => 'abc123',
        ]);
    }

    public function test_control_routes_are_hidden_when_disabled_and_reject_wrong_tokens(): void
    {
        config(['services.paypal.e2e.enabled' => false]);

        $this->postJson('/qa/paypal-e2e/prepare', ['run_reference' => 'run-1'], $this->headers())
            ->assertNotFound();

        config(['services.paypal.e2e.enabled' => true]);

        $this->postJson('/qa/paypal-e2e/prepare', ['run_reference' => 'run-1'], [
            'Authorization' => 'Bearer wrong-token',
        ])->assertUnauthorized();

        config(['services.paypal.base_url' => 'https://api-m.paypal.com']);

        $this->postJson('/qa/paypal-e2e/prepare', ['run_reference' => 'run-1'], $this->headers())
            ->assertNotFound();
    }

    public function test_prepare_creates_a_unique_fixture_without_deleting_financial_history(): void
    {
        $control = app(PayPalSandboxE2eControl::class);

        $this->postJson('/qa/paypal-e2e/prepare', ['run_reference' => 'run-1'], $this->headers())
            ->assertOk()
            ->assertExactJson([
                'status' => 'ready',
                'paypal_api_environment' => 'sandbox',
                'paypal_client_reference' => $this->reference('sandbox-client-id'),
                'paypal_webhook_reference' => $this->reference('sandbox-webhook-id'),
                'deployed_revision' => 'abc123',
            ]);

        $firstFixture = User::query()->where('email', $control->fixtureEmail('run-1'))->sole();
        $historicalOrder = $this->completedOrder($firstFixture, 'PAYPAL-E2E-HISTORY-1-merch');

        $this->postJson('/qa/paypal-e2e/prepare', ['run_reference' => 'run-1'], $this->headers())
            ->assertConflict();

        $this->postJson('/qa/paypal-e2e/prepare', ['run_reference' => 'run-2'], $this->headers())
            ->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $historicalOrder->id]);
        $this->assertDatabaseHas('users', ['email' => $control->fixtureEmail('run-2')]);
        $this->assertNotSame($control->fixtureEmail('run-1'), $control->fixtureEmail('run-2'));
    }

    public function test_fault_controls_are_one_shot_scoped_and_state_contains_only_hmac_references(): void
    {
        $control = app(PayPalSandboxE2eControl::class);
        $control->prepareExistingCustomer('target-run');
        $targetUser = User::query()->where('email', $control->fixtureEmail('target-run'))->sole();
        $otherUser = User::factory()->create();

        $this->postJson('/qa/paypal-e2e/arm', ['run_reference' => 'target-run'], $this->headers())
            ->assertOk()
            ->assertExactJson(['status' => 'armed']);

        $order = Order::create([
            'user_id' => $targetUser->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-E2E-STATE-1-merch',
            'provider_capture_id' => 'CAPTURE-E2E-STATE',
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'currency' => 'USD',
            'status' => 'payment_review',
            'grants_royal_month' => true,
        ]);

        $this->assertFalse($control->consumeBrowserPersistFailure($otherUser, 'PAYPAL-OTHER'));
        $this->assertFalse($control->shouldHoldCaptureWebhook('PAYPAL-OTHER'));
        $this->assertTrue($control->shouldHoldCaptureWebhook('PAYPAL-E2E-STATE'));
        $this->assertTrue($control->consumeBrowserPersistFailure($targetUser, 'PAYPAL-E2E-STATE'));
        $this->assertFalse($control->consumeBrowserPersistFailure($targetUser, 'PAYPAL-E2E-STATE'));
        $this->assertTrue($control->shouldHoldCaptureWebhook('PAYPAL-E2E-STATE'));

        $this->postJson('/qa/paypal-e2e/state', [
            'paypal_order_id' => 'PAYPAL-E2E-STATE',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('order_reference', $this->reference('PAYPAL-E2E-STATE'))
            ->assertJsonPath('capture_reference', $this->reference('CAPTURE-E2E-STATE'))
            ->assertJsonPath('order_count', 1)
            ->assertJsonPath('statuses.payment_review', 1)
            ->assertJsonPath('refund_count', 0)
            ->assertJsonMissing(['paypal_order_id' => 'PAYPAL-E2E-STATE'])
            ->assertJsonMissing(['provider_capture_id' => 'CAPTURE-E2E-STATE']);

        $this->postJson('/qa/paypal-e2e/release', ['paypal_order_id' => 'PAYPAL-OTHER'], $this->headers())
            ->assertConflict();
        $this->assertTrue($control->shouldHoldCaptureWebhook('PAYPAL-E2E-STATE'));

        $this->postJson('/qa/paypal-e2e/release', ['paypal_order_id' => 'PAYPAL-E2E-STATE'], $this->headers())
            ->assertOk()
            ->assertExactJson(['status' => 'released']);
        $this->assertFalse($control->shouldHoldCaptureWebhook('PAYPAL-E2E-STATE'));
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

    private function reference(string $value): string
    {
        return substr(hash_hmac('sha256', $value, 'sandbox-reference-key'), 0, 16);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer sandbox-control-token'];
    }
}
