@extends('admin.layout')
@section('title', 'Yoycol · Merch')
@section('admin_section', 'store')
@section('content')
<div class="yoycol-admin">
    <h1>Yoycol · Productos y pedidos</h1>
    <p>Importa los diseños, define el precio de venta y publica. El cliente paga en PayPal; fabricación y envío se pagan por separado en Yoycol.</p>
    @unless (config('yoycol.enabled'))<p role="alert">Integración desactivada. Configura las nuevas credenciales y YOYCOL_ENABLED antes de vender.</p>@endunless
    <form method="POST" action="{{ route('admin.yoycol.sync') }}">@csrf<button class="yoycol-button">Sincronizar diseños</button></form>
    <h2>Catálogo</h2>
    <div class="yoycol-admin-grid">
        @forelse ($products as $product)
            <form class="yoycol-panel" method="POST" action="{{ route('admin.yoycol.update', $product) }}">
                @csrf @method('PATCH')
                @if ($product->image_url)<img class="yoycol-admin-image" src="{{ $product->image_url }}" alt="{{ $product->title }}">@endif
                <p>{{ $product->published ? 'Publicado' : 'Borrador' }} · {{ count($product->variants) }} variantes · Diseño {{ $product->design_code }}</p>
                <label>Nombre<input name="title" value="{{ $product->title }}" maxlength="160" required></label>
                <label>Descripción<textarea name="description" maxlength="3000">{{ $product->description }}</textarea></label>
                <p>Costo base: @if (count($product->variants))${{ number_format(collect($product->variants)->min('cost_cents') / 100, 2) }}–${{ number_format(collect($product->variants)->max('cost_cents') / 100, 2) }} USD @else No disponible @endif · Envío aparte</p>
                <label>Precio al cliente (USD)<input name="price" type="number" step="0.01" min="0.01" max="10000" value="{{ $product->price_cents ? number_format($product->price_cents / 100, 2, '.', '') : '' }}"></label>
                <label class="yoycol-check"><input type="checkbox" name="published" value="1" @checked($product->published)> Publicar en la tienda</label>
                <button class="yoycol-button">Guardar</button>
                @if ($product->purchasable())<a href="{{ route('store.checkout', $product->productKey()) }}">Ver producto</a>@endif
            </form>
        @empty<p>No hay diseños importados. Usa “Sincronizar diseños”.</p>@endforelse
    </div>
    <h2>Pedidos y pagos al proveedor</h2>
    <p>Yoycol no ofrece Auto-Pay para tiendas API propias. Entra a Yoycol, busca el número del pedido y paga fabricación y envío. El estado se actualiza cada 15 minutos.</p>
    <a href="https://www.yoycol.com" target="_blank" rel="noopener noreferrer">Abrir Yoycol</a>
    @forelse ($fulfillments as $fulfillment)
        <article class="yoycol-panel">
            <h3>{{ data_get($fulfillment->order->metadata, 'product.title') }}</h3>
            <p>Referencia: {{ $fulfillment->store_order_sn }}</p>
            <p>Yoycol: {{ data_get($fulfillment->provider_summary, 'orderNo', $fulfillment->provider_order_id ?? 'Pendiente de envío') }}</p>
            <p>Cliente: {{ $fulfillment->order->status }} · ${{ number_format($fulfillment->order->amount_cents / 100, 2) }} USD</p>
            <p>Proveedor: <strong>{{ $fulfillment->state }}</strong> · {{ data_get($fulfillment->provider_summary, 'statusLabel') }}</p>
            @if (data_get($fulfillment->provider_summary, 'orderRealAmount') !== null)<p>Importe informado por Yoycol: {{ $fulfillment->provider_summary['orderRealAmount'] }} {{ $fulfillment->provider_summary['currency'] ?? 'USD' }}. Verifica el desglose final antes de pagar.</p>@endif
            @if ($fulfillment->state === 'payment_required')<p role="alert">Pago a Yoycol pendiente. Paga este pedido en Yoycol para iniciar producción.</p>@endif
            @if ($fulfillment->state === 'refund_review')<p role="alert">Hay un reembolso del cliente. Revisa/cancela el pedido en Yoycol antes de producir.</p>@endif
            @if ($fulfillment->last_error)<p role="alert">{{ $fulfillment->last_error }}</p>@endif
            @if (data_get($fulfillment->provider_summary, 'needApprovalReason'))<p>{{ $fulfillment->provider_summary['needApprovalReason'] }}</p>@endif
            @if (data_get($fulfillment->tracking, 'waybillCode'))<p>Seguimiento: {{ $fulfillment->tracking['waybillCode'] }}</p>@endif
            <form method="POST" action="{{ route('admin.yoycol.reconcile', $fulfillment) }}">@csrf<button class="yoycol-button">Actualizar estado</button></form>
            @if ($fulfillment->state === 'review' && ! $fulfillment->provider_order_id)
                <form method="POST" action="{{ route('admin.yoycol.retry', $fulfillment) }}">@csrf
                    <label class="yoycol-check"><input type="checkbox" name="confirmed_absent" value="1" required> Verifiqué en Yoycol que esta referencia no existe. Autorizar un nuevo intento.</label>
                    <button class="yoycol-button">Reconciliar y reintentar</button>
                </form>
            @endif
            @if ($fulfillment->provider_order_id && $fulfillment->state !== 'cancelled')
                <form method="POST" action="{{ route('admin.yoycol.cancel', $fulfillment) }}">@csrf
                    <label class="yoycol-check"><input type="checkbox" name="confirmed_cancel" value="1" required> Cancelar el pedido al proveedor. El reembolso de PayPal se gestiona por separado.</label>
                    <button class="yoycol-button">Cancelar en Yoycol</button>
                </form>
            @endif
        </article>
    @empty<p>Aún no hay pedidos de merch.</p>@endforelse
    {{ $fulfillments->links() }}
</div>
@endsection
