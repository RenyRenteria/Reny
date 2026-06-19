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
}
