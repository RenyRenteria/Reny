<?php

namespace App\Services\Commerce;

use App\Models\AccessEvent;
use App\Models\BillingProfile;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\User;
use App\Support\PayPalReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PayPalSandboxE2eControl
{
    private const FAIL_BROWSER_PERSIST_KEY_PREFIX = 'paypal_sandbox_e2e.fail_browser_persist.';

    private const HOLD_CAPTURE_WEBHOOK_KEY_PREFIX = 'paypal_sandbox_e2e.hold_capture_webhook.';

    public function authorize(Request $request): void
    {
        abort_unless($this->enabled(), 404);

        $expected = (string) config('services.paypal.e2e.control_token');
        $provided = (string) $request->bearerToken();

        abort_unless($expected !== '' && $provided !== '' && hash_equals($expected, $provided), 401);
    }

    public function enabled(): bool
    {
        return ! app()->environment('production')
            && (bool) config('services.paypal.e2e.enabled')
            && rtrim((string) config('services.paypal.base_url'), '/') === 'https://api-m.sandbox.paypal.com';
    }

    /**
     * @return array{paypal_api_environment: string, paypal_client_reference: string|null, paypal_webhook_reference: string|null, deployed_revision: string|null}
     */
    public function configuration(): array
    {
        $clientId = (string) config('services.paypal.client_id', '');
        $webhookId = (string) config('services.paypal.webhook_id', '');
        $revision = trim((string) config('services.paypal.e2e.release_sha', ''));

        return [
            'paypal_api_environment' => 'sandbox',
            'paypal_client_reference' => filled($clientId) ? $this->reference($clientId) : null,
            'paypal_webhook_reference' => filled($webhookId) ? $this->reference($webhookId) : null,
            'deployed_revision' => filled($revision) ? $revision : null,
        ];
    }

    public function prepareExistingCustomer(string $runReference): void
    {
        $email = $this->fixtureEmail($runReference);

        DB::transaction(function () use ($email): void {
            $user = User::query()->firstOrCreate([
                'email' => $email,
            ], [
                'name' => 'PayPal Sandbox Existing Customer',
                'password' => Hash::make(Str::password(32)),
                'role' => User::ROLE_FAN,
            ]);

            abort_if(
                Order::query()->where('user_id', $user->id)->exists(),
                409,
                'Sandbox E2E run reference has already created financial records.'
            );

            $user->forceFill([
                'name' => 'PayPal Sandbox Existing Customer',
                'phone' => null,
                'country_code' => null,
                'royal_status' => 'open',
                'royal_ends_at' => null,
            ])->save();
        });

    }

    public function armPostCaptureFailure(string $runReference): void
    {
        $user = User::query()->where('email', $this->fixtureEmail($runReference))->first();

        abort_unless($user !== null, 409, 'Prepare the sandbox E2E fixture before arming a fault.');
        abort_if(Order::query()->where('user_id', $user->id)->exists(), 409, 'Arm the fault before creating an order.');

        Cache::put($this->failureKey($user->id), true, now()->addMinutes(15));
    }

    public function consumeBrowserPersistFailure(User $user, string $paypalOrderId): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if (! (bool) Cache::pull($this->failureKey($user->id), false)) {
            return false;
        }

        Cache::put($this->holdKey($paypalOrderId), true, now()->addMinutes(15));

        return true;
    }

    public function shouldHoldCaptureWebhook(?string $paypalOrderId): bool
    {
        if (! $this->enabled() || blank($paypalOrderId)) {
            return false;
        }

        if ((bool) Cache::get($this->holdKey((string) $paypalOrderId), false)) {
            return true;
        }

        $userId = Order::query()
            ->where('provider', 'paypal')
            ->where(function ($query) use ($paypalOrderId) {
                $query
                    ->where('provider_order_id', $paypalOrderId)
                    ->orWhereRaw('provider_order_id LIKE ? ESCAPE ?', [
                        $this->escapeLike((string) $paypalOrderId).'-%',
                        '\\',
                    ]);
            })
            ->value('user_id');

        return $userId !== null && (bool) Cache::get($this->failureKey((int) $userId), false);
    }

    public function releaseCaptureWebhook(string $paypalOrderId): void
    {
        abort_unless(
            (bool) Cache::pull($this->holdKey($paypalOrderId), false),
            409,
            'No matching sandbox webhook hold exists.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function checkoutState(string $paypalOrderId): array
    {
        $orders = Order::query()
            ->with('user')
            ->where('provider', 'paypal')
            ->whereRaw('provider_order_id LIKE ? ESCAPE ?', [
                $this->escapeLike($paypalOrderId).'-%',
                '\\',
            ])
            ->orderBy('id')
            ->get();
        $user = $orders->first()?->user;
        $captureIds = $orders->pluck('provider_capture_id')->filter()->unique()->values();

        return [
            'order_reference' => $this->reference($paypalOrderId),
            'capture_reference' => $captureIds->count() === 1 ? $this->reference((string) $captureIds->first()) : null,
            'order_count' => $orders->count(),
            'statuses' => $orders->countBy('status')->sortKeys()->all(),
            'royal_status' => $user?->accessState()->value,
            'billing_profile_count' => $user ? BillingProfile::query()->where('user_id', $user->id)->count() : 0,
            'purchase_event_count' => $user ? AccessEvent::query()->where('user_id', $user->id)->where('event_name', 'purchase')->count() : 0,
            'membership_event_count' => $user ? AccessEvent::query()->where('user_id', $user->id)->where('event_name', 'membership_started')->count() : 0,
            'membership_expired_event_count' => $user ? AccessEvent::query()->where('user_id', $user->id)->where('event_name', 'membership_expired')->count() : 0,
            'refund_count' => OrderRefund::query()->whereIn('order_id', $orders->pluck('id'))->count(),
        ];
    }

    public function fixtureEmail(string $runReference): string
    {
        return 'qa+paypal-'.substr(hash('sha256', $runReference), 0, 20).'@renyrenteria.test';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function failureKey(int $userId): string
    {
        return self::FAIL_BROWSER_PERSIST_KEY_PREFIX.$userId;
    }

    private function holdKey(string $paypalOrderId): string
    {
        return self::HOLD_CAPTURE_WEBHOOK_KEY_PREFIX.hash('sha256', $paypalOrderId);
    }

    private function reference(string $value): string
    {
        return PayPalReference::hash($value, (string) config('services.paypal.e2e.reference_key'));
    }
}
