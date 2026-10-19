@extends('yoycol.layout')
@section('title', 'Order tracking')
@section('content')
<a class="yoycol-back" href="{{ route('account.show') }}">← My account</a>
<section class="yoycol-panel">
    <p class="yoycol-eyebrow">YOUR MERCH ORDER</p>
    <h1>{{ data_get($order->metadata, 'product.title', 'Official merch') }}</h1>
    <p>{{ data_get($order->metadata, 'merch.variant') }} · Quantity {{ data_get($order->metadata, 'merch.quantity', 1) }}</p>
    <h2>{{ $fulfillment->customerStatus() }}</h2>
    <p>Total paid: ${{ number_format($order->amount_cents / 100, 2) }} USD</p>
    @if (data_get($fulfillment->tracking, 'waybillCode'))
        <p>{{ data_get($fulfillment->tracking, 'lpName') ?: data_get($fulfillment->tracking, 'carrier') }}</p>
        <p>Tracking number: <strong>{{ data_get($fulfillment->tracking, 'waybillCode') }}</strong></p>
        @if (data_get($fulfillment->tracking, 'tailTrackCode'))<p>Local tracking: {{ $fulfillment->tracking['tailTrackCode'] }}</p>@endif
        <a href="https://www.17track.net/en/track?nums={{ rawurlencode($fulfillment->tracking['waybillCode']) }}" target="_blank" rel="noopener noreferrer">Track delivery</a>
        <ol>
            @foreach (array_merge($fulfillment->tracking['headTrackingEvents'] ?? [], $fulfillment->tracking['tailTrackingEvents'] ?? []) as $event)
                <li>{{ $event['description'] ?? '' }} @if ($event['location'] ?? null) · {{ $event['location'] }} @endif</li>
            @endforeach
        </ol>
    @else
        <p>Your tracking number will appear here when your order ships.</p>
    @endif
    @if ($fulfillment->synced_at)<p>Last updated {{ $fulfillment->synced_at->diffForHumans() }}.</p>@endif
    <a href="{{ route('contacto') }}">Contact us about this order</a>
</section>
@endsection
