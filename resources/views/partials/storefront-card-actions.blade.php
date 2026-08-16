@php
    $cardButtonClass = $cardButtonClass ?? 'store-button store-button-light';
    $showCompactCountdown = $showCompactCountdown ?? false;
@endphp

@if ($isFreeLeadEvent)
    <button
        class="{{ $cardButtonClass }}"
        type="button"
        data-free-event-rsvp="{{ $slotProductKey }}"
        data-free-event-name="{{ $slot['title'] }}"
        data-free-event-price="{{ $isShowsPage ? $showVisiblePrice : $slotVisiblePrice }}"
        data-free-event-rsvp-endpoint="{{ route('community.free-event-rsvp.store') }}"
    >{{ $slot['cta_label'] ?? 'GET TICKETS' }}</button>
@elseif ($slotActionType === 'rsvp')
    <button
        class="{{ $cardButtonClass }}"
        type="button"
        data-rsvp="{{ $slotProductKey }}"
        data-rsvp-name="{{ $slot['title'] }}"
        data-rsvp-endpoint="{{ route('store.rsvp') }}"
        data-rsvp-status-target="{{ $slotStatusId }}"
        data-rsvp-confirmed="{{ $rsvpTicket ? 'true' : 'false' }}"
        aria-describedby="{{ $slotStatusId }}"
    >{{ $rsvpTicket ? 'RSVP confirmed' : ($slot['cta_label'] ?? 'GET TICKETS') }}</button>
@elseif ($slotActionType === 'link')
    @if (filled($slot['url'] ?? null))
        <a class="{{ $cardButtonClass }}" href="{{ $slot['url'] }}" target="_blank" rel="noreferrer">{{ $slot['cta_label'] ?? 'OPEN' }}</a>
    @else
        <span class="{{ $cardButtonClass }}" aria-disabled="true">Unavailable</span>
    @endif
@else
    <button
        class="{{ $cardButtonClass }}"
        type="button"
        data-buy="{{ $slotProductKey }}"
        data-buy-name="{{ $slot['title'] }}"
        data-buy-type="{{ $slotType($slot) }}"
        data-buy-summary="{{ str_replace("\n", ' - ', $slot['description'] ?? '') }}"
        data-buy-image="{{ $slotImage($slot) }}"
        @if ($slotHasExchangeablePrice) data-buy-price-value="{{ number_format($slotPriceValue, 2, '.', '') }}" @endif
        data-buy-url="{{ route('store.checkout', ['product' => $slotProductKey]) }}"
    >{{ $slot['cta_label'] ?? 'BUY' }}</button>
@endif

@if ($showCompactCountdown && $countdownTarget && $countdownLabel)
    <span
        class="storefront-countdown"
        data-countdown-at="{{ $countdownTarget->toIso8601String() }}"
        data-countdown-ended-label="Today"
        aria-live="polite"
    >{{ $countdownLabel }}</span>
@endif

@if ($slotActionType === 'rsvp' && ! $isFreeLeadEvent)
    <p class="storefront-rsvp-status sr-only {{ $rsvpTicket ? 'is-confirmed' : '' }}" id="{{ $slotStatusId }}">
        @if ($rsvpTicket)
            Reserved - {{ str_replace('_', ' ', $rsvpTicket['status']) }} - Code {{ $rsvpTicket['code'] }}
        @else
            RSVP confirms a reservation on this account.
        @endif
    </p>
@endif
