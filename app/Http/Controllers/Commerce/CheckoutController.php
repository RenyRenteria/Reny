<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\ProductCatalog;
use App\Services\PayPalService;
use App\Services\RoyalPassService;
use App\Services\UserHubPurchaseSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    private const SETTLEMENT_CURRENCY = 'USD';

    private const CHECKOUT_SESSION_TOKEN_KEY = 'checkout.public_token';

    private const GUEST_CUSTOMER_IDS_SESSION_KEY = 'checkout.guest_customer_ids';

    public function __construct(private readonly ProductCatalog $products) {}

    public function store(
        Request $request,
        RoyalPassService $royalPass,
        PayPalService $payPal,
        UserHubPurchaseSync $purchaseSync,
    ): JsonResponse {
        $validated = $this->validateCheckout($request, requirePaypalOrder: true);

        $currency = $this->currency($validated);
        $authenticatedUser = Auth::user();
        $checkoutTokenHash = $this->activeCheckoutTokenHash($request);

        $pendingOrders = $this->pendingPayPalOrders($validated['paypal_order_id'], $authenticatedUser, $checkoutTokenHash);
        $this->ensurePendingPayPalOrders($validated['paypal_order_id'], $pendingOrders);
        $user = $this->pendingOrdersUser($pendingOrders);

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
            $orders = DB::transaction(function () use ($authenticatedUser, $capture, $checkoutTokenHash, $purchaseSync, $royalPass, $user, $validated) {
                $orders = $this->pendingPayPalOrders($validated['paypal_order_id'], $authenticatedUser, $checkoutTokenHash, lock: true);
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

        if (! $authenticatedUser) {
            Auth::login($user);
            $request->session()->regenerate();
        }

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
        $validated = $this->validateCheckout($request, requireCustomerDetails: true);
        $currency = $this->currency($validated);
        $user = $this->resolveCheckoutCustomer($request, $royalPass, $validated['identifier']);
        $checkoutTokenHash = $this->checkoutTokenHash($request);
        $products = $this->resolveProducts($validated['product_keys'], $user);

        $pendingReference = 'PENDING-'.Str::upper(Str::random(20));
        $orders = DB::transaction(function () use ($checkoutTokenHash, $currency, $pendingReference, $products, $user, $validated) {
            return $products->map(function (array $product, int $index) use ($checkoutTokenHash, $currency, $pendingReference, $user, $validated) {
                return Order::create([
                    'user_id' => $user->id,
                    'provider' => 'paypal',
                    'provider_order_id' => $this->providerOrderId($pendingReference, $product['key'], $index),
                    'product_key' => $product['key'],
                    'amount_cents' => $product['amount_cents'],
                    'currency' => $currency,
                    'status' => 'pending',
                    'grants_royal_month' => true,
                    'metadata' => $this->orderMetadata($product, $validated, $checkoutTokenHash),
                ]);
            });
        });

        try {
            $order = $payPal->createOrder(
                $orders->sum('amount_cents'),
                $currency,
                $this->paypalItems($orders),
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
        $checkoutTokenHash = $this->activeCheckoutTokenHash($request);

        $pendingOrders = $this->pendingPayPalOrders($validated['paypal_order_id'], $user, $checkoutTokenHash);
        $this->ensurePendingPayPalOrders($validated['paypal_order_id'], $pendingOrders);

        $updated = Order::query()
            ->whereKey($pendingOrders->pluck('id'))
            ->where('status', 'pending')
            ->whereNull('provider_capture_id')
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

        $user = $this->resolveCheckoutCustomer($request, $royalPass, $validated['identifier']);
        $checkoutTokenHash = $this->checkoutTokenHash($request);
        $products = $this->resolveProducts($validated['product_keys'], $user);

        $orders = DB::transaction(function () use ($checkoutTokenHash, $currency, $products, $reference, $royalPass, $user, $validated) {
            return $products->map(function (array $product, int $index) use ($checkoutTokenHash, $currency, $reference, $royalPass, $user, $validated) {
                $providerOrderId = $this->providerOrderId("LOCAL-{$reference}", $product['key'], $index);
                $order = Order::create([
                    'user_id' => $user->id,
                    'provider' => 'local',
                    'provider_order_id' => $providerOrderId,
                    'product_key' => $product['key'],
                    'amount_cents' => $product['amount_cents'],
                    'currency' => $currency,
                    'status' => 'pending',
                    'grants_royal_month' => false,
                    'metadata' => $this->orderMetadata($product, $validated, $checkoutTokenHash),
                ]);

                $royalPass->log($user, 'purchase_pending', 'order', $providerOrderId, [
                    'product_key' => $product['key'],
                    'provider' => 'local',
                    'local_reference' => $reference,
                ]);

                return $order;
            });
        });

        $payload = [
            'status' => 'pending',
            'message' => 'Local payment reference received. Access will update after manual confirmation.',
            'order_ids' => $orders->pluck('provider_order_id')->values(),
        ];

        if (Auth::check()) {
            $payload['account_url'] = route('account.show');
        }

        return response()->json($payload);
    }

    /**
     * @return array{identifier: string, product_keys: array<int, string>, currency?: string, paypal_order_id?: string, local_reference?: string, customer_name?: string, customer_email?: string, customer_phone?: string, customer_country?: string}
     */
    private function validateCheckout(
        Request $request,
        bool $requirePaypalOrder = false,
        bool $requireLocalReference = false,
        bool $requireCustomerDetails = false,
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
            'product_keys.*' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'currency' => ['nullable', 'string', 'size:3'],
            'customer_name' => [$requireCustomerDetails ? 'required' : 'sometimes', 'string', 'max:120'],
            'customer_email' => [$requireCustomerDetails ? 'required' : 'sometimes', 'email', 'max:255'],
            'customer_phone' => [$requireCustomerDetails ? 'required' : 'sometimes', 'string', 'max:32', 'regex:/^\+[1-9][0-9]{6,14}$/'],
            'customer_country' => [$requireCustomerDetails ? 'required' : 'sometimes', 'string', 'max:80'],
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
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function orderMetadata(array $product, array $validated, ?string $checkoutTokenHash = null): array
    {
        $metadata = [
            'product' => $this->products->orderSnapshot($product),
        ];

        if ($checkoutTokenHash) {
            $metadata['checkout'] = [
                'session_token_hash' => $checkoutTokenHash,
            ];
        }

        $customer = array_filter([
            'name' => $validated['customer_name'] ?? null,
            'email' => $validated['customer_email'] ?? null,
            'phone' => $validated['customer_phone'] ?? null,
            'country' => $validated['customer_country'] ?? null,
        ], fn (mixed $value): bool => filled($value));

        if ($customer !== []) {
            $metadata['customer'] = $customer;
        }

        return $metadata;
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
     * @param  array<int, string>  $productKeys
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveProducts(array $productKeys, User $user): Collection
    {
        $products = $this->products->findMany($productKeys, $user);
        $missing = collect($productKeys)
            ->filter(fn (string $productKey, int $index): bool => $products->get($index) === null)
            ->values();

        if ($missing->isEmpty()) {
            return $products->filter()->values();
        }

        throw ValidationException::withMessages([
            'product_keys' => 'Some products are no longer available for checkout: '.$missing->implode(', '),
        ]);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, array{name: string, unit_amount_cents: int, quantity: int}>
     */
    private function paypalItems(Collection $orders): array
    {
        return $orders
            ->groupBy(fn (Order $order): string => $order->product_key.'|'.$order->amount_cents)
            ->map(function (Collection $group): array {
                /** @var Order $order */
                $order = $group->first();

                return [
                    'name' => (string) data_get($order->metadata, 'product.title', str($order->product_key)->headline()->toString()),
                    'unit_amount_cents' => $order->amount_cents,
                    'quantity' => $group->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Order>
     */
    private function pendingPayPalOrders(
        string $paypalOrderId,
        ?User $user = null,
        ?string $checkoutTokenHash = null,
        bool $lock = false,
    ): Collection {
        $query = Order::query()
            ->where('provider', 'paypal')
            ->where('status', 'pending')
            ->where('provider_order_id', 'like', "{$paypalOrderId}-%")
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $orders = $query->get();

        if (! $checkoutTokenHash) {
            return collect();
        }

        return $orders
            ->filter(function (Order $order) use ($checkoutTokenHash, $user): bool {
                if ($user && $order->user_id !== $user->id) {
                    return false;
                }

                return hash_equals(
                    $checkoutTokenHash,
                    (string) data_get($order->metadata, 'checkout.session_token_hash', ''),
                );
            })
            ->values();
    }

    private function pendingOrdersUser(Collection $orders): User
    {
        $userIds = $orders
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($userIds->count() !== 1) {
            throw ValidationException::withMessages([
                'paypal_order_id' => 'This checkout is missing a valid customer account.',
            ]);
        }

        $user = User::query()->find($userIds->first());

        if (! $user) {
            throw ValidationException::withMessages([
                'paypal_order_id' => 'This checkout is missing a valid customer account.',
            ]);
        }

        return $user;
    }

    private function resolveCheckoutCustomer(Request $request, RoyalPassService $royalPass, string $identifier): User
    {
        $authenticatedUser = Auth::user();

        if ($authenticatedUser) {
            return $authenticatedUser;
        }

        $existingCustomer = $royalPass->findCustomer($identifier);

        if ($existingCustomer) {
            if ($this->sessionOwnsGuestCustomer($request, $existingCustomer)) {
                return $existingCustomer;
            }

            throw ValidationException::withMessages([
                'identifier' => 'Log in to checkout with this email or phone.',
            ]);
        }

        $user = $royalPass->createCustomer($identifier);
        $this->rememberGuestCustomer($request, $user);

        return $user;
    }

    private function checkoutTokenHash(Request $request): string
    {
        $token = $request->session()->get(self::CHECKOUT_SESSION_TOKEN_KEY);

        if (! is_string($token) || $token === '') {
            $token = Str::random(40);
            $request->session()->put(self::CHECKOUT_SESSION_TOKEN_KEY, $token);
        }

        return hash('sha256', $token);
    }

    private function activeCheckoutTokenHash(Request $request): ?string
    {
        $token = $request->session()->get(self::CHECKOUT_SESSION_TOKEN_KEY);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return hash('sha256', $token);
    }

    private function rememberGuestCustomer(Request $request, User $user): void
    {
        $customerIds = $request->session()->get(self::GUEST_CUSTOMER_IDS_SESSION_KEY, []);

        if (! is_array($customerIds)) {
            $customerIds = [];
        }

        $customerIds[] = $user->id;

        $request->session()->put(
            self::GUEST_CUSTOMER_IDS_SESSION_KEY,
            collect($customerIds)->map(fn (mixed $id): int => (int) $id)->unique()->values()->all(),
        );
    }

    private function sessionOwnsGuestCustomer(Request $request, User $user): bool
    {
        $customerIds = $request->session()->get(self::GUEST_CUSTOMER_IDS_SESSION_KEY, []);

        if (! is_array($customerIds)) {
            return false;
        }

        return in_array($user->id, collect($customerIds)->map(fn (mixed $id): int => (int) $id)->all(), true);
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
