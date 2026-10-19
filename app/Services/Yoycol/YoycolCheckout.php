<?php

namespace App\Services\Yoycol;

use App\Models\Order;
use App\Models\YoycolFulfillment;
use App\Models\YoycolProduct;
use App\Models\YoycolQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class YoycolCheckout
{
    public function __construct(private readonly YoycolClient $client) {}

    public function quote(Request $request, YoycolProduct $product): array
    {
        abort_unless($product->purchasable(), 404);
        $data = $request->validate([
            'variant_id' => ['required', 'string', 'max:80'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'shipping_level' => ['nullable', 'string', 'max:80'],
            'address' => ['required', 'array'],
            'address.firstName' => ['required', 'string', 'max:60'],
            'address.lastName' => ['required', 'string', 'max:60'],
            'address.email' => ['required', 'email', 'max:255'],
            'address.phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{6,14}$/'],
            'address.country' => ['required', Rule::in(array_keys(config('yoycol.countries')))],
            'address.province' => ['required', 'string', 'max:100'],
            'address.city' => ['required', 'string', 'max:100'],
            'address.address1' => ['required', 'string', 'max:200'],
            'address.address2' => ['nullable', 'string', 'max:200'],
            'address.zip' => ['required', 'string', 'max:20'],
        ]);
        $data['quantity'] = (int) $data['quantity'];
        $variant = collect($product->variants)->firstWhere('id', $data['variant_id']);
        if (! $variant) {
            throw ValidationException::withMessages(['variant_id' => 'This size is no longer available.']);
        }
        $regions = $this->client->request('GET', 'shipping/sku_quotes', ['sku_code' => $variant['sku']]);
        $regionName = config('yoycol.countries.'.$data['address']['country'].'.region');
        $region = collect($regions)->firstWhere('regionName', $regionName);
        $levels = collect($region['levels'] ?? [])->filter(fn (array $level): bool => filled($level['levelCode'] ?? null) && is_numeric($level['firstPrice'] ?? null)
            && $level['firstPrice'] >= 0 && ($data['quantity'] === 1 || (is_numeric($level['additionalPrice'] ?? null) && $level['additionalPrice'] >= 0))
        )->map(fn (array $level): array => [
            'code' => $level['levelCode'], 'name' => $level['levelName'],
            'description' => $level['description'] ?? '',
            'amount_cents' => (int) round($level['firstPrice'] * 100) + ($data['quantity'] - 1) * (int) round(($level['additionalPrice'] ?? 0) * 100),
        ])->sortBy('amount_cents')->values();
        $selected = empty($data['shipping_level']) ? $levels->first() : $levels->firstWhere('code', $data['shipping_level']);
        if (! $selected) {
            throw ValidationException::withMessages(['shipping' => 'Shipping is unavailable for this size and destination. No payment was taken.']);
        }
        $subtotal = $product->price_cents * $data['quantity'];
        $quote = YoycolQuote::create([
            'id' => (string) Str::uuid(), 'yoycol_product_id' => $product->id,
            'session_hash' => $this->sessionHash($request),
            'amount_cents' => $subtotal + $selected['amount_cents'],
            'expires_at' => now()->addMinutes(config('yoycol.quote_minutes')),
            'snapshot' => [
                'address' => $data['address'], 'variant' => $variant, 'design_code' => $product->design_code,
                'quantity' => $data['quantity'], 'unit_price_cents' => $product->price_cents,
                'subtotal_cents' => $subtotal, 'shipping' => $selected,
            ],
        ]);

        return ['quote_id' => $quote->id, 'expires_at' => $quote->expires_at->toIso8601String(),
            'subtotal_cents' => $subtotal, 'shipping_cents' => $selected['amount_cents'],
            'amount_cents' => $quote->amount_cents, 'currency' => 'USD', 'shipping_level' => $selected['code'], 'levels' => $levels];
    }

    // Called inside the same database transaction that creates the pending PayPal order.
    public function prepare(Request $request, Collection $products, array $validated, bool $paypal): Collection
    {
        if (! $products->contains(fn (array $p): bool => $p['source_type'] === 'yoycol_product')) {
            return $products;
        }
        if (! $paypal || $products->count() !== 1) {
            throw ValidationException::withMessages(['product_keys' => 'Please check out this merch item separately using PayPal.']);
        }
        $quote = YoycolQuote::whereKey($validated['yoycol_quote_id'] ?? '')->lockForUpdate()->first();
        $product = YoycolProduct::find($products->first()['source_id']);
        if (! $quote || ! $product || ! $product->purchasable() || $quote->yoycol_product_id !== $product->id
            || $quote->consumed_at || $quote->expires_at->isPast()
            || ! hash_equals($quote->session_hash, $this->sessionHash($request))) {
            throw ValidationException::withMessages(['shipping' => 'Please calculate shipping again before paying.']);
        }
        $snapshot = $quote->snapshot;
        $variant = collect($product->variants)->firstWhere('id', $snapshot['variant']['id']);
        if (! $variant || $variant['sku'] !== $snapshot['variant']['sku'] || $product->design_code !== $snapshot['design_code']
            || $product->price_cents !== $snapshot['unit_price_cents']) {
            throw ValidationException::withMessages(['shipping' => 'This product changed. Please calculate shipping again.']);
        }
        $quote->update(['consumed_at' => now()]);

        return collect([[...$products->first(), 'amount_cents' => $quote->amount_cents, 'yoycol' => $snapshot]]);
    }

    public function attach(Order $order, array $product): void
    {
        if (! isset($product['yoycol'])) {
            return;
        }
        $s = $product['yoycol'];
        $reference = 'RENY-'.Str::uuid();
        YoycolFulfillment::create([
            'order_id' => $order->id, 'store_order_sn' => $reference,
            'request_payload' => [
                'storeOrderSn' => $reference, 'currency' => 'USD', 'address' => $s['address'],
                'shippingLevelCode' => $s['shipping']['code'], 'autoSwitchShippingLevel' => false,
                'thirdCreatedAt' => $order->created_at->toIso8601String(),
                'items' => [[
                    'thirdItemId' => (string) $order->id, 'productId' => $product['key'],
                    // The API supports direct template design + SKU without a remote store mapping.
                    'skuCode' => $s['variant']['sku'], 'designCode' => $s['design_code'], 'source' => 'productTemplate',
                    'title' => $product['title'], 'image' => $s['variant']['image_url'] ?? $product['image_url'],
                    'quantity' => $s['quantity'], 'salesPrice' => $s['unit_price_cents'] / 100,
                    'realPrice' => $s['unit_price_cents'] / 100,
                ]],
            ],
        ]);
    }

    public function paypalDelivery(Collection $orders): ?array
    {
        $fulfillment = $orders->count() === 1 ? $orders->first()->yoycolFulfillment : null;
        if (! $fulfillment) {
            return null;
        }
        $address = $fulfillment->request_payload['address'];

        return [
            'amount_cents' => data_get($orders->first()->metadata, 'merch.shipping_cents'),
            'shipping' => [
                'name' => ['full_name' => $address['firstName'].' '.$address['lastName']],
                'address' => array_filter([
                    'address_line_1' => $address['address1'], 'address_line_2' => $address['address2'] ?? null,
                    'admin_area_1' => $address['province'], 'admin_area_2' => $address['city'],
                    'postal_code' => $address['zip'], 'country_code' => $address['country'],
                ], fn ($value) => filled($value)),
            ],
        ];
    }

    public function beginCapture(Collection $orders): void
    {
        $ids = $orders->filter(fn (Order $order) => str_starts_with($order->product_key, 'yoycol-'))->pluck('id');
        DB::transaction(function () use ($ids): void {
            foreach (Order::whereKey($ids)->orderBy('id')->lockForUpdate()->get() as $order) {
                if ($order->status !== 'pending') {
                    throw ValidationException::withMessages(['paypal_order_id' => 'This order is no longer pending.']);
                }
                $metadata = $order->metadata;
                $metadata['checkout']['capture_started'] = true;
                $order->update(['metadata' => $metadata]);
            }
        });
    }

    private function sessionHash(Request $request): string
    {
        $token = $request->session()->get('yoycol.quote_token');
        if (! is_string($token)) {
            $token = Str::random(64);
            $request->session()->put('yoycol.quote_token', $token);
        }

        return hash('sha256', $token);
    }
}
