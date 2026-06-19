<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PayPalService;
use App\Services\RoyalPassService;
use App\Services\UserHubPurchaseSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
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
        $expectedTotal = $this->expectedTotal($validated['product_keys']);
        $capture = $payPal->captureOrder($validated['paypal_order_id'], $expectedTotal, $currency);

        $user = Auth::user() ?: $royalPass->findOrCreateCustomer($validated['identifier']);
        Auth::login($user);

        $orders = DB::transaction(function () use ($capture, $currency, $purchaseSync, $royalPass, $user, $validated) {
            return collect($validated['product_keys'])->map(function (string $productKey, int $index) use ($currency, $capture, $purchaseSync, $user, $royalPass) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'provider' => 'paypal',
                    'provider_order_id' => $this->providerOrderId($capture['order_id'], $productKey, $index),
                    'product_key' => $productKey,
                    'amount_cents' => $this->prices[$productKey],
                    'currency' => $currency,
                    'status' => 'completed',
                    'grants_royal_month' => true,
                ]);

                $royalPass->log($user, 'purchase', 'order', $order->provider_order_id, [
                    'product_key' => $productKey,
                    'provider' => 'paypal',
                    'paypal_capture_id' => $capture['capture_id'],
                ]);

                $royalPass->grantMonth($user, $order);
                $purchaseSync->recordCompletedOrder($user, $order, $capture);

                return $order;
            });
        });

        $request->session()->regenerate();

        return response()->json([
            'status' => 'completed',
            'royal_status' => $user->fresh()->accessState()->value,
            'royal_ends_at' => $user->fresh()->royal_ends_at?->toIso8601String(),
            'order_ids' => $orders->pluck('provider_order_id')->values(),
            'account_url' => route('account.show'),
        ]);
    }

    public function createOrder(Request $request, PayPalService $payPal): JsonResponse
    {
        $validated = $this->validateCheckout($request);
        $order = $payPal->createOrder(
            $this->expectedTotal($validated['product_keys']),
            $this->currency($validated)
        );

        return response()->json([
            'status' => 'created',
            'paypal_order_id' => $order['order_id'],
        ]);
    }

    /**
     * @return array{identifier: string, product_keys: array<int, string>, currency?: string, paypal_order_id?: string}
     */
    private function validateCheckout(Request $request, bool $requirePaypalOrder = false): array
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'product_keys' => ['required', 'array', 'min:1'],
            'product_keys.*' => ['required', 'string', Rule::in(array_keys($this->prices))],
            'currency' => ['nullable', 'string', 'size:3'],
            'paypal_order_id' => [$requirePaypalOrder ? 'required' : 'sometimes', 'string', 'max:255'],
        ]);

        $identifier = trim($validated['identifier']);
        $phoneDigits = preg_replace('/\D+/', '', $identifier) ?? '';

        if (! filter_var($identifier, FILTER_VALIDATE_EMAIL) && strlen($phoneDigits) < 7) {
            throw ValidationException::withMessages([
                'identifier' => 'Use a valid email or phone number.',
            ]);
        }

        $validated['identifier'] = $identifier;

        return $validated;
    }

    /**
     * @param  array<int, string>  $productKeys
     */
    private function expectedTotal(array $productKeys): int
    {
        return collect($productKeys)->sum(fn (string $productKey) => $this->prices[$productKey]);
    }

    /**
     * @param  array{currency?: string}  $validated
     */
    private function currency(array $validated): string
    {
        return strtoupper($validated['currency'] ?? 'USD');
    }

    private function providerOrderId(string $paypalOrderId, string $productKey, int $index): string
    {
        return $paypalOrderId.'-'.($index + 1).'-'.$productKey;
    }
}
