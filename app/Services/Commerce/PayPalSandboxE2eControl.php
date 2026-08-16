<?php

namespace App\Services\Commerce;

use App\Models\AccessEvent;
use App\Models\BillingProfile;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserUnlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PayPalSandboxE2eControl
{
    private const FAIL_BROWSER_PERSIST_KEY = 'paypal_sandbox_e2e.fail_browser_persist';

    private const HOLD_CAPTURE_WEBHOOK_KEY = 'paypal_sandbox_e2e.hold_capture_webhook';

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
     * @return array{paypal_api_environment: string, paypal_client_reference: string|null, paypal_webhook_reference: string|null}
     */
    public function configuration(): array
    {
        $clientId = (string) config('services.paypal.client_id', '');
        $webhookId = (string) config('services.paypal.webhook_id', '');

        return [
            'paypal_api_environment' => 'sandbox',
            'paypal_client_reference' => filled($clientId) ? $this->reference($clientId) : null,
            'paypal_webhook_reference' => filled($webhookId) ? $this->reference($webhookId) : null,
        ];
    }

    public function prepareExistingCustomer(): void
    {
        $email = Str::lower(trim((string) config('services.paypal.e2e.existing_customer_email')));

        $isDedicatedFixture = str_starts_with($email, 'qa+paypal-')
            && str_ends_with($email, '@renyrenteria.test');

        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL) && $isDedicatedFixture, 503, 'Sandbox E2E customer is not configured.');

        DB::transaction(function () use ($email): void {
            $user = User::query()->firstOrCreate([
                'email' => $email,
            ], [
                'name' => 'PayPal Sandbox Existing Customer',
                'password' => Hash::make(Str::password(32)),
                'role' => User::ROLE_FAN,
            ]);
            $orderIds = Order::query()->where('user_id', $user->id)->pluck('id');

            OrderRefund::query()->whereIn('order_id', $orderIds)->delete();
            Ticket::query()->whereIn('order_id', $orderIds)->delete();
            UserUnlock::query()->where('user_id', $user->id)->delete();
            BillingProfile::query()->where('user_id', $user->id)->delete();
            AccessEvent::query()->where('user_id', $user->id)->delete();
            Order::query()->whereKey($orderIds)->delete();

            $user->forceFill([
                'name' => 'PayPal Sandbox Existing Customer',
                'phone' => null,
                'country_code' => null,
                'royal_status' => 'open',
                'royal_ends_at' => null,
            ])->save();
        });

        Cache::forget(self::FAIL_BROWSER_PERSIST_KEY);
        Cache::forget(self::HOLD_CAPTURE_WEBHOOK_KEY);
    }

    public function armPostCaptureFailure(): void
    {
        Cache::put(self::FAIL_BROWSER_PERSIST_KEY, true, now()->addMinutes(15));
        Cache::put(self::HOLD_CAPTURE_WEBHOOK_KEY, true, now()->addMinutes(15));
    }

    public function consumeBrowserPersistFailure(): bool
    {
        return $this->enabled() && (bool) Cache::pull(self::FAIL_BROWSER_PERSIST_KEY, false);
    }

    public function shouldHoldCaptureWebhook(): bool
    {
        return $this->enabled() && (bool) Cache::get(self::HOLD_CAPTURE_WEBHOOK_KEY, false);
    }

    public function releaseCaptureWebhook(): void
    {
        Cache::forget(self::HOLD_CAPTURE_WEBHOOK_KEY);
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
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function reference(string $value): string
    {
        return substr(hash('sha256', $value), 0, 16);
    }
}
