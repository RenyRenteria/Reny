@php
    $isShowsPage = ($storePage ?? 'store') === 'shows';
    $activeNavigation = $isShowsPage ? 'shows' : 'store';
    $storefront = $publicCms['storefront'] ?? app(\App\Services\StorefrontSettingsService::class)->publicPayload();
    $royalPass = $storefront['royal_pass'] ?? [];
    $royalProductKey = $royalPass['product_key'] ?? 'royal';
    $royalCtaLabel = $royalPass['cta_label'] ?? 'Unlock Royal Pass';
    $storefrontSlots = collect($isShowsPage
        ? ['event_primary', 'event_secondary']
        : ['event_primary', 'event_secondary', 'merch'])
        ->map(fn (string $key): array => data_get($storefront, "slots.{$key}", []))
        ->filter()
        ->values();
    $rsvpTickets = $rsvpTickets ?? [];
    $isGuestPreview = (bool) request()->attributes->get('site_editor_guest_preview', false);
    $shouldShowRoyalPass = $isGuestPreview || auth()->guest();
    $slotImage = fn (array $slot): string => $slot['image_url'] ?? asset($slot['image'] ?? 'images/store/work-in-progress.png');
    $slotType = fn (array $slot): string => $slot['eyebrow'] ?: str($slot['kind'] ?? 'product')->headline()->toString();
    $isFreeEventPrice = function (string $price): bool {
        if (preg_match('/(^|[^a-z])free([^a-z]|$)/i', $price) === 1) {
            return true;
        }

        $numeric = preg_replace('/[^0-9.]/', '', $price);

        return in_array($numeric, ['0', '0.0', '0.00'], true);
    };
    $storeTimezone = config('admin.publishing_timezone', config('app.timezone', 'UTC'));
    $slotCountdownTarget = function (?string $value) use ($storeTimezone) {
        if (! filled($value)) {
            return null;
        }

        try {
            return \Carbon\CarbonImmutable::parse($value, $storeTimezone);
        } catch (\Throwable) {
            return null;
        }
    };
    $slotCountdownLabel = function ($target): ?string {
        if (! $target) {
            return null;
        }

        $seconds = (int) max(0, now()->diffInSeconds($target, false));

        if ($seconds <= 0) {
            return 'Today';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);

        if ($days > 0) {
            return "{$days}D {$hours}H";
        }

        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return "{$hours}H {$minutes}M";
        }

        return max(1, $minutes).'M';
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $isShowsPage ? 'Shows' : 'Store' }} | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;500&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="{{ $isShowsPage ? 'shows' : 'store' }}" data-preferred-currency="{{ auth()->user()?->preferred_currency ?? 'USD' }}">
        <div class="store-shell" data-public-page-root>
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <x-public-navigation :active="$activeNavigation" />
                </div>

                <x-member-card />
            </aside>

            <main class="main-content store-content" id="{{ $isShowsPage ? 'shows' : 'store' }}">
                <header class="mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                            <img
                                class="brand-logo"
                                src="{{ asset('images/reny-renteria-logo.png') }}"
                                alt="Reny Renteria"
                            >
                        </a>
                    </div>
                </header>

                @if (! $isShowsPage && $shouldShowRoyalPass)
                    <section
                        class="store-royal-pass is-selected"
                        data-royal-pass-container
                        data-royal-pass-selected="true"
                    >
                        <button
                            class="store-royal-pass-selector"
                            type="button"
                            aria-label="Select Royal Pass"
                            aria-pressed="true"
                            data-royal-pass-option="{{ $royalProductKey }}"
                        >
                            <span class="store-royal-pass-copy">
                                {{ $royalPass['copy_before'] ?? 'Get your' }}
                                <strong>{{ $royalPass['emphasis'] ?? 'Royal Pass' }}</strong>
                                {{ $royalPass['copy_after'] ?? 'to unlock exclusive content, community and more' }}
                            </span>
                        </button>
                        <button
                            class="store-button store-royal-pass-button"
                            type="button"
                            data-buy="{{ $royalProductKey }}"
                            data-buy-name="{{ $royalPass['emphasis'] ?? 'Royal Pass' }}"
                            data-buy-type="Membership"
                            data-buy-summary="Monthly membership with exclusive content, community and more."
                            data-buy-image="{{ asset('images/store/royal-pass.png') }}"
                            data-buy-url="{{ route('store.checkout', ['product' => $royalProductKey]) }}"
                            data-royal-pass-cta
                            aria-disabled="false"
                            aria-label="{{ $royalCtaLabel }}"
                        >{{ $royalCtaLabel }}</button>
                    </section>
                @endif

                <section class="storefront" aria-label="{{ $isShowsPage ? 'Shows' : 'Store products' }}">
                    <div class="storefront-grid">
                        @foreach ($storefrontSlots as $slot)
                            @php
                                $slotKey = $slot['key'] ?? 'slot-'.$loop->index;
                                $slotProductKey = $slot['product_key'] ?? $slotKey;
                                $slotActionType = $slot['action_type'] ?? 'buy';
                                $slotVisiblePrice = trim((string) ($slot['price_label'] ?? ''));
                                $isFreeLeadEvent = ($slot['kind'] ?? '') === 'event' && $isFreeEventPrice($slotVisiblePrice);
                                $slotPriceValue = (float) preg_replace('/[^0-9.]/', '', $slotVisiblePrice);
                                $slotHasExchangeablePrice = filled($slotVisiblePrice) && ! $isFreeLeadEvent && $slotPriceValue > 0;
                                $slotStatusId = 'rsvp-status-' . \Illuminate\Support\Str::slug($slotProductKey);
                                $rsvpTicket = $rsvpTickets[$slotProductKey] ?? null;
                                $countdownTarget = ($slot['kind'] ?? '') === 'event'
                                    ? $slotCountdownTarget($slot['countdown_at'] ?? null)
                                    : null;
                                $countdownLabel = $slotCountdownLabel($countdownTarget);
                            @endphp
                            <article @class([
                                'storefront-card',
                                'is-event' => ($slot['kind'] ?? '') === 'event',
                                'is-product' => ($slot['kind'] ?? '') !== 'event',
                            ])>
                                <img
                                    class="storefront-image"
                                    src="{{ $slotImage($slot) }}"
                                    alt="{{ $slot['image_alt'] ?? $slot['title'] }}"
                                    loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                    decoding="async"
                                >
                                <div class="storefront-copy">
                                    @if (filled($slot['eyebrow'] ?? null))
                                        <span>{{ $slot['eyebrow'] }}</span>
                                    @endif
                                    <h2>{{ $slot['title'] }}</h2>
                                    <p>{!! nl2br(e($slot['description'] ?? '')) !!}</p>
                                    @if (filled($slotVisiblePrice))
                                        <strong
                                            class="storefront-price"
                                            @if ($slotHasExchangeablePrice)
                                                data-price="{{ $slotProductKey }}"
                                                data-price-value="{{ number_format($slotPriceValue, 2, '.', '') }}"
                                            @endif
                                        >{{ $slotVisiblePrice }}</strong>
                                    @endif

                                    <div class="storefront-action-row">
                                        @if ($isFreeLeadEvent)
                                            <button
                                                class="store-button store-button-light"
                                                type="button"
                                                data-free-event-rsvp="{{ $slotProductKey }}"
                                                data-free-event-name="{{ $slot['title'] }}"
                                                data-free-event-price="{{ $slotVisiblePrice }}"
                                                data-free-event-rsvp-endpoint="{{ route('community.free-event-rsvp.store') }}"
                                            >{{ $slot['cta_label'] ?? 'GET TICKETS' }}</button>
                                        @elseif ($slotActionType === 'rsvp')
                                            <button
                                                class="store-button store-button-light"
                                                type="button"
                                                data-rsvp="{{ $slotProductKey }}"
                                                data-rsvp-name="{{ $slot['title'] }}"
                                                data-rsvp-endpoint="{{ route('store.rsvp') }}"
                                                data-rsvp-status-target="{{ $slotStatusId }}"
                                                data-rsvp-confirmed="{{ $rsvpTicket ? 'true' : 'false' }}"
                                                aria-describedby="{{ $slotStatusId }}"
                                            >{{ $rsvpTicket ? 'RSVP confirmed' : ($slot['cta_label'] ?? 'GET TICKETS') }}</button>
                                        @elseif ($slotActionType === 'link' && filled($slot['url'] ?? null))
                                            <a class="store-button store-button-light" href="{{ $slot['url'] }}" target="_blank" rel="noreferrer">{{ $slot['cta_label'] ?? 'OPEN' }}</a>
                                        @else
                                            <button
                                                class="store-button store-button-light"
                                                type="button"
                                                data-buy="{{ $slotProductKey }}"
                                                data-buy-name="{{ $slot['title'] }}"
                                                data-buy-type="{{ $slotType($slot) }}"
                                                data-buy-summary="{{ str_replace("\n", ' - ', $slot['description'] ?? '') }}"
                                                data-buy-image="{{ $slotImage($slot) }}"
                                                data-buy-url="{{ route('store.checkout', ['product' => $slotProductKey]) }}"
                                            >{{ $slot['cta_label'] ?? 'BUY' }}</button>
                                        @endif

                                        @if ($countdownTarget && $countdownLabel)
                                            <span
                                                class="storefront-countdown"
                                                data-countdown-at="{{ $countdownTarget->toIso8601String() }}"
                                                data-countdown-ended-label="Today"
                                                aria-live="polite"
                                            >{{ $countdownLabel }}</span>
                                        @endif
                                    </div>

                                    @if ($slotActionType === 'rsvp' && ! $isFreeLeadEvent)
                                        <p
                                            class="storefront-rsvp-status sr-only {{ $rsvpTicket ? 'is-confirmed' : '' }}"
                                            id="{{ $slotStatusId }}"
                                        >
                                            @if ($rsvpTicket)
                                                Reserved - {{ str_replace('_', ' ', $rsvpTicket['status']) }} - Code {{ $rsvpTicket['code'] }}
                                            @else
                                                RSVP confirms a reservation on this account.
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <x-public-navigation :active="$activeNavigation" mobile />
            </main>
        </div>

        @include('partials.store-checkout-modals')
        @include('partials.music-player-modal')
    </body>
</html>
