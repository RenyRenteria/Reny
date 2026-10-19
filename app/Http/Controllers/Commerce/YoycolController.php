<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\YoycolProduct;
use App\Services\Yoycol\YoycolCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class YoycolController extends Controller
{
    public function quote(Request $request, YoycolProduct $product, YoycolCheckout $checkout): JsonResponse
    {
        return response()->json($checkout->quote($request, $product));
    }

    public function tracking(Request $request, Order $order): View
    {
        abort_unless($request->user()->id === $order->user_id, 404);
        $fulfillment = $order->yoycolFulfillment;
        abort_unless($fulfillment && in_array($order->status, ['completed', 'refunded', 'payment_review'], true), 404);

        return view('yoycol.tracking', compact('order', 'fulfillment'));
    }
}
