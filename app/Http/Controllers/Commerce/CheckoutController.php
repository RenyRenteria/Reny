<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PayPalService;
use App\Services\RoyalPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    /**
     * @var array<string, int>
     */
    private array $prices = [
        'deluxe' => 2400,
        'singles' => 800,
        'royal' => 1900,
        'merch' => 4800,
        'print' => 8600,
        'concert' => 4200,
        'listening' => 1800,
    ];

    public function store(Request $request, RoyalPassService $royalPass, PayPalService $payPal): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'product_keys' => ['required', 'array', 'min:1'],
            'product_keys.*' => ['required', 'string', Rule::in(array_keys($this->prices))],
            'currency' => ['nullable', 'string', 'size:3'],
            'paypal_order_id' => ['required', 'string', 'max:255'],
        ]);

        $currency = strtoupper($validated['currency'] ?? 'USD');
        $expectedTotal = collect($validated['product_keys'])
            ->sum(fn (string $productKey) => $this->prices[$productKey]);
        $capture = $payPal->captureOrder($validated['paypal_order_id'], $expectedTotal, $currency);

        $user = Auth::user() ?: $royalPass->findOrCreateCustomer($validated['identifier']);
        Auth::login($user);

        $orders = collect($validated['product_keys'])->map(function (string $productKey) use ($currency, $capture, $user, $royalPass) {
            $order = Order::create([
                'user_id' => $user->id,
                'provider' => 'paypal',
                'provider_order_id' => $capture['order_id'].'-'.$productKey,
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

            return $order;
        });

        $request->session()->regenerate();

        return response()->json([
            'status' => 'completed',
            'royal_status' => $user->fresh()->accessState()->value,
            'royal_ends_at' => $user->fresh()->royal_ends_at?->toIso8601String(),
            'order_ids' => $orders->pluck('provider_order_id')->values(),
        ]);
    }
}
