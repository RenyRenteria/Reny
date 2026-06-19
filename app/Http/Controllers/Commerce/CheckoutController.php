<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\PayPalService;
use App\Services\RoyalPassService;
use App\Services\UserHubPurchaseSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    private const SETTLEMENT_CURRENCY = 'USD';

    /**
     * @var array<string, int>
     */
    private array $prices = [
        'deluxe' => 2400,
        'singles' => 800,
        'royal' => 499,
        'merch' => 4800,
        'print' => 8600,
        'concert' => 4200,
        'listening' => 1800,
    ];

    public function store(
        Request $request,
        RoyalPassService $royalPass,
        PayPalService $payPal,
        UserHubPurchaseSync $purchaseSync,
    ): JsonResponse {
        $validated = $this->validateCheckout($request, requirePaypalOrder: true);

        $currency = $this->currency($validated);
        $user = Auth::user() ?: $royalPass->findOrCreateCustomer($validated['identifier']);
        Auth::login($user);

        $pendingOrders = $this->pendingPayPalOrders($validated['paypal_order_id'], $user);
        $this->ensurePendingPayPalOrders($validated['paypal_order_id'], $pendingOrders);

        $capture = $payPal->captureOrder(
            $validated['paypal_order_id'],
            $pendingOrders->sum('amount_cents'),
            $this->orderCurrency($pendingOrders, $currency),
        );

        if ($capture['order_id'] !== $validated['paypal_order_id']) {
            throw ValidationException::withMessages([
                'paypal_order_id' => 'PayPal returned a different order than this checkout.',
            ]);
        }

        $this->ensureUnusedPayPalCapture($capture, $pendingOrders);

        try {
            $orders = DB::transaction(function () use ($capture, $purchaseSync, $royalPass, $user, $validated) {
                $orders = $this->pendingPayPalOrders($validated['paypal_order_id'], $user, lock: true);
                $this->ensurePendingPayPalOrders($validated['paypal_order_id'], $orders);
                $this->ensureUnusedPayPalCapture($capture, $orders);

                return $orders->map(function (Order $order) use ($capture, $purchaseSync, $royalPass, $user) {
                    $order->forceFill([
                        'provider_capture_id' => $capture['capture_id'],
                        'status' => 'completed',
                    ])->save();

                    $royalPass->log($user, 'purchase', 'order', $order->provider_order_id, [
                        'product_key' => $order->product_key,
                        'provider' => 'paypal',
                        'paypal_capture_id' => $capture['capture_id'],
                    ]);

                    $royalPass->grantMonth($user, $order);
                    $purchaseSync->recordCompletedOrder($user, $order, $capture);

                    return $order;
                });
            });
        } catch (Throwable $exception) {
            $this->markCapturedPayPalOrderForReview($validated['paypal_order_id'], $user, $capture);

            throw $exception;
        }

        $request->session()->regenerate();

        return response()->json([
            'status' => 'completed',
            'royal_status' => $user->fresh()->accessState()->value,
            'royal_ends_at' => $user->fresh()->royal_ends_at?->toIso8601String(),
            'order_ids' => $orders->pluck('provider_order_id')->values(),
            'account_url' => route('account.show'),
        ]);
    }

    public function createOrder(Request $request, PayPalService $payPal, RoyalPassService $royalPass): JsonResponse
    {
        $validated = $this->validateCheckout($request);
        $currency = $this->currency($validated);
        $user = Auth::user() ?: $royalPass->findOrCreateCustomer($validated['identifier']);
        Auth::login($user);

        $pendingReference = 'PENDING-'.Str::upper(Str::random(20));
        $orders = DB::transaction(function () use ($currency, $pendingReference, $user, $validated) {
            return collect($validated['product_keys'])->map(function (string $productKey, int $index) use ($currency, $pendingReference, $user) {
                return Order::create([
                    'user_id' => $user->id,
                    'provider' => 'paypal',
                    'provider_order_id' => $this->providerOrderId($pendingReference, $productKey, $index),
                    'product_key' => $productKey,
                    'amount_cents' => $this->prices[$productKey],
                    'currency' => $currency,
                    'status' => 'pending',
                    'grants_royal_month' => true,
                ]);
            });
        });

        try {
            $order = $payPal->createOrder(
                $orders->sum('amount_cents'),
                $currency
            );

            $orders = DB::transaction(function () use ($order, $orders) {
                return $orders->values()->map(function (Order $pendingOrder, int $index) use ($order) {
                    $pendingOrder = Order::query()
                        ->whereKey($pendingOrder->id)
                        ->where('status', 'pending')
                        ->lockForUpdate()
                        ->firstOrFail();

                    $pendingOrder->forceFill([
                        'provider_order_id' => $this->providerOrderId($order['order_id'], $pendingOrder->product_key, $index),
                    ])->save();

                    return $pendingOrder;
                });
            });
        } catch (Throwable $exception) {
            Order::query()
                ->whereKey($orders->pluck('id'))
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);

            throw $exception;
        }

        $request->session()->regenerate();

        return response()->json([
            'status' => 'created',
            'paypal_order_id' => $order['order_id'],
            'order_ids' => $orders->pluck('provider_order_id')->values(),
        ]);
    }

    public function cancelOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paypal_order_id' => ['required', 'string', 'max:255'],
        ]);

        $user = Auth::user();

        if (! $user) {
            throw ValidationException::withMessages([
                'paypal_order_id' => 'Create a PayPal order before canceling checkout.',
            ]);
        }

        $updated = Order::query()
            ->where('provider', 'paypal')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereNull('provider_capture_id')
            ->where('provider_order_id', 'like', "{$validated['paypal_order_id']}-%")
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'paypal_order_id' => 'This PayPal order cannot be canceled.',
            ]);
        }

        return response()->json([
            'status' => 'cancelled',
            'cancelled_orders' => $updated,
        ]);
    }

    public function local(Request $request, RoyalPassService $royalPass): JsonResponse
    {
        $validated = $this->validateCheckout($request, requireLocalReference: true);
        $currency = $this->currency($validated);
        $reference = $this->normalizeLocalReference($validated['local_reference']);

        $this->ensureUnusedLocalReference($reference);

        $user = Auth::user() ?: $royalPass->findOrCreateCustomer($validated['identifier']);
        Auth::login($user);

        $orders = DB::transaction(function () use ($currency, $reference, $royalPass, $user, $validated) {
            return collect($validated['product_keys'])->map(function (string $productKey, int $index) use ($currency, $reference, $royalPass, $user) {
                $providerOrderId = $this->providerOrderId("LOCAL-{$reference}", $productKey, $index);
                $order = Order::create([
                    'user_id' => $user->id,
                    'provider' => 'local',
                    'provider_order_id' => $providerOrderId,
                    'product_key' => $productKey,
                    'amount_cents' => $this->prices[$productKey],
                    'currency' => $currency,
                    'status' => 'pending',
                    'grants_royal_month' => false,
                ]);

                $royalPass->log($user, 'purchase_pending', 'order', $providerOrderId, [
                    'product_key' => $productKey,
                    'provider' => 'local',
                    'local_reference' => $reference,
                ]);

                return $order;
            });
        });

        $request->session()->regenerate();

        return response()->json([
            'status' => 'pending',
            'message' => 'Local payment reference received. Access will update after manual confirmation.',
            'order_ids' => $orders->pluck('provider_order_id')->values(),
            'account_url' => route('account.show'),
        ]);
    }

    /**
     * @return array{identifier: string, product_keys: array<int, string>, currency?: string, paypal_order_id?: string, local_reference?: string}
     */
    private function validateCheckout(
        Request $request,
        bool $requirePaypalOrder = false,
        bool $requireLocalReference = false,
    ): array {
        return $request->validate([
            'identifier' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->isValidIdentifier((string) $value)) {
                        $fail('Enter a valid email or phone.');
                    }
                },
            ],
            'product_keys' => ['required', 'array', 'min:1'],
            'product_keys.*' => ['required', 'string', Rule::in(array_keys($this->prices))],
            'currency' => ['nullable', 'string', 'size:3'],
            'paypal_order_id' => [$requirePaypalOrder ? 'required' : 'sometimes', 'string', 'max:255'],
            'local_reference' => [
                $requireLocalReference ? 'required' : 'sometimes',
                'string',
                'min:6',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->isValidLocalReference((string) $value)) {
                        $fail('Enter a valid receipt or reference with at least 4 digits.');
                    }
                },
            ],
        ]);
    }

    /**
     * @param  array{currency?: string}  $validated
     */
    private function currency(array $validated): string
    {
        $currency = strtoupper($validated['currency'] ?? self::SETTLEMENT_CURRENCY);

        if ($currency !== self::SETTLEMENT_CURRENCY) {
            throw ValidationException::withMessages([
                'currency' => 'Checkout currently settles in USD.',
            ]);
        }

        return $currency;
    }

    private function providerOrderId(string $paypalOrderId, string $productKey, int $index): string
    {
        return $paypalOrderId.'-'.($index + 1).'-'.$productKey;
    }

    /**
     * @return Collection<int, Order>
     */
    private function pendingPayPalOrders(string $paypalOrderId, User $user, bool $lock = false): Collection
    {
        $query = Order::query()
            ->where('provider', 'paypal')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('provider_order_id', 'like', "{$paypalOrderId}-%")
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function isValidIdentifier(string $value): bool
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 7 && strlen($digits) <= 15;
    }

    private function isValidLocalReference(string $value): bool
    {
        $reference = $this->normalizeLocalReference($value);
        $digits = preg_replace('/\D+/', '', $reference) ?? '';

        return strlen($reference) >= 6
            && strlen($reference) <= 64
            && strlen($digits) >= 4
            && (bool) preg_match('/^[A-Z0-9][A-Z0-9-]*[A-Z0-9]$/', $reference);
    }

    private function normalizeLocalReference(string $value): string
    {
        return str($value)
            ->trim()
            ->replaceMatches('/\s+/', '-')
            ->upper()
            ->toString();
    }

    private function ensureUnusedLocalReference(string $reference): void
    {
        if (! Order::query()
            ->where('provider', 'local')
            ->where('provider_order_id', 'like', "LOCAL-{$reference}-%")
            ->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'local_reference' => 'This local payment reference has already been submitted.',
        ]);
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function ensurePendingPayPalOrders(string $paypalOrderId, Collection $orders): void
    {
        if ($orders->isNotEmpty()) {
            return;
        }

        $message = Order::query()
            ->where('provider', 'paypal')
            ->where('provider_order_id', 'like', "{$paypalOrderId}-%")
            ->exists()
                ? 'This PayPal order belongs to a different checkout or is no longer pending.'
                : 'Create a PayPal order before capture.';

        throw ValidationException::withMessages([
            'paypal_order_id' => $message,
        ]);
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function orderCurrency(Collection $orders, string $fallback): string
    {
        $currencies = $orders->pluck('currency')->unique()->values();

        if ($currencies->count() > 1) {
            throw ValidationException::withMessages([
                'currency' => 'Pending checkout contains mixed currencies.',
            ]);
        }

        return (string) ($currencies->first() ?? $fallback);
    }

    /**
     * @param  array{order_id: string, capture_id: string, payer_id: string|null, payload: array<string, mixed>}  $capture
     * @param  Collection<int, Order>  $pendingOrders
     */
    private function ensureUnusedPayPalCapture(array $capture, Collection $pendingOrders): void
    {
        $paypalOrderId = $capture['order_id'];
        $paypalCaptureId = $capture['capture_id'];
        $pendingOrderIds = $pendingOrders->pluck('id');

        if (! Order::query()
            ->where('provider', 'paypal')
            ->whereNotIn('id', $pendingOrderIds)
            ->where(function ($query) use ($paypalCaptureId, $paypalOrderId) {
                $query
                    ->where('provider_capture_id', $paypalCaptureId)
                    ->orWhere('provider_order_id', 'like', "{$paypalOrderId}-%");
            })
            ->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'paypal_order_id' => 'This PayPal payment has already been recorded.',
        ]);
    }

    /**
     * @param  array{order_id: string, capture_id: string, payer_id: string|null, payload: array<string, mixed>}  $capture
     */
    private function markCapturedPayPalOrderForReview(string $paypalOrderId, User $user, array $capture): void
    {
        Order::query()
            ->where('provider', 'paypal')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('provider_order_id', 'like', "{$paypalOrderId}-%")
            ->update([
                'provider_capture_id' => $capture['capture_id'],
                'status' => 'payment_review',
                'updated_at' => now(),
            ]);
    }
}
