<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\YoycolFulfillment;
use App\Models\YoycolProduct;
use App\Services\Yoycol\YoycolCatalog;
use App\Services\Yoycol\YoycolFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class YoycolController extends Controller
{
    public function index(): View
    {
        return view('admin.yoycol', [
            'products' => YoycolProduct::orderBy('id')->get(),
            'fulfillments' => YoycolFulfillment::with('order')->latest()->paginate(25),
        ]);
    }

    public function sync(YoycolCatalog $catalog): RedirectResponse
    {
        $count = $catalog->sync();

        return back()->with('status', "{$count} diseños sincronizados. Los nuevos quedan como borradores.");
    }

    public function update(Request $request, YoycolProduct $product): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'price' => ['nullable', 'decimal:0,2', 'numeric', 'min:0.01', 'max:10000'],
            'published' => ['sometimes', 'boolean'],
        ]);
        $price = isset($data['price']) ? (int) round($data['price'] * 100) : null;
        if ($request->boolean('published') && (! $price || ! count($product->variants) || ! $product->synced_at?->gt(now()->subDay()))) {
            throw ValidationException::withMessages(['published' => 'Define el precio y sincroniza las variantes antes de publicar.']);
        }
        $product->update([
            'title' => $data['title'], 'description' => $data['description'] ?? null,
            'price_cents' => $price, 'published' => $request->boolean('published'),
        ]);

        return back()->with('status', 'Producto guardado.');
    }

    public function reconcile(YoycolFulfillment $fulfillment, YoycolFulfillmentService $service): RedirectResponse
    {
        $service->process($fulfillment);

        return back()->with('status', 'Pedido revisado. Consulta su estado y cualquier aviso pendiente.');
    }

    public function retry(Request $request, YoycolFulfillment $fulfillment, YoycolFulfillmentService $service): RedirectResponse
    {
        $request->validate(['confirmed_absent' => ['accepted']]);
        $service->retry($fulfillment);

        return back()->with('status', 'Reconciliación y reintento completados. Consulta el estado del pedido.');
    }

    public function cancel(Request $request, YoycolFulfillment $fulfillment, YoycolFulfillmentService $service): RedirectResponse
    {
        $request->validate(['confirmed_cancel' => ['accepted']]);
        $service->cancel($fulfillment);

        return back()->with('status', 'Pedido cancelado en Yoycol. El reembolso al cliente se gestiona por separado en PayPal.');
    }
}
