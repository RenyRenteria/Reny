<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RoyalPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaypalWebhookController extends Controller
{
    public function refund(Request $request, RoyalPassService $royalPass): JsonResponse
    {
        $validated = $request->validate([
            'provider_order_id' => ['required', 'string', 'exists:orders,provider_order_id'],
        ]);

        $order = Order::where('provider_order_id', $validated['provider_order_id'])->firstOrFail();

        $royalPass->revokeGrant($order);

        return response()->json([
            'status' => 'refunded',
            'royal_status' => $order->user?->fresh()->accessState()->value,
        ]);
    }
}
