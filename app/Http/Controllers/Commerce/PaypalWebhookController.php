<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PayPalService;
use App\Services\RoyalPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PaypalWebhookController extends Controller
{
    public function refund(Request $request, RoyalPassService $royalPass, PayPalService $payPal): JsonResponse
    {
        abort_unless($payPal->verifyWebhook($request), 401);
        abort_unless($request->input('event_type') === 'PAYMENT.CAPTURE.REFUNDED', 422);

        $providerOrderId = $this->providerOrderId($request);

        abort_unless($providerOrderId, 422, 'Missing PayPal order reference.');

        $orders = Order::query()
            ->where('provider_order_id', $providerOrderId)
            ->orWhere('provider_order_id', 'like', "{$providerOrderId}-%")
            ->get();

        abort_if($orders->isEmpty(), 404);

        $orders->each(fn (Order $order) => $royalPass->revokeGrant($order));

        return response()->json([
            'status' => 'refunded',
            'refunded_orders' => $orders->count(),
            'royal_status' => $orders->first()->user?->fresh()->accessState()->value,
        ]);
    }

    private function providerOrderId(Request $request): ?string
    {
        return $request->input('provider_order_id')
            ?: Arr::get($request->all(), 'resource.custom_id')
            ?: Arr::get($request->all(), 'resource.supplementary_data.related_ids.order_id');
    }
}
