@php
    $isShowsPage = ($storePage ?? 'store') === 'shows';
    $activeNavigation = $isShowsPage ? 'shows' : 'store';
    $storefront = $publicCms['storefront'] ?? app(\App\Services\StorefrontSettingsService::class)->publicPayload();
    $pageSettings = $isShowsPage ? [] : ($publicCms['page'] ?? []);
    $royalPass = $storefront['royal_pass'] ?? [];
    $baseStorefrontSlots = collect($isShowsPage
        ? ['event_primary', 'event_secondary']
        : ['event_primary', 'event_secondary', 'album', 'merch'])
        ->map(fn (string $key): array => data_get($storefront, "slots.{$key}", []))
        ->filter()
        ->values();
    $moneyLabel = function (int $amountCents, string $currency): string {
        $prefix = strtoupper($currency) === 'USD' ? '$' : strtoupper($currency).' ';

        return $prefix.number_format($amountCents / 100, $amountCents % 100 === 0 ? 0 : 2);
    };
    $cmsEventSlots = collect($publicCms['events'] ?? [])->map(function (array $event) use ($moneyLabel): array {
        $isRsvp = ($event['mode'] ?? null) === 'rsvp';

        return [
            'key' => 'cms-event-'.($event['key'] ?? ''),
            'kind' => 'event',
            'title' => $event['name'] ?? 'Event',
            'eyebrow' => $event['kicker'] ?? 'Event',
            'description' => trim(($event['summary'] ?? '')."\n".($event['place'] ?? '')."\n".($event['date'] ?? '')),
            'price_label' => $isRsvp
                ? 'FREE'
                : (($event['mode'] ?? 'buy') === 'buy'
                    ? $moneyLabel((int) ($event['amount_cents'] ?? 0), (string) ($event['currency'] ?? 'USD'))
                    : ''),
            'cta_label' => $event['action'] ?? ($isRsvp ? 'RSVP' : 'BUY TICKETS'),
            'countdown_at' => $event['starts_at'] ?? null,
            'timezone' => $event['timezone'] ?? null,
            'action_type' => $event['mode'] ?? 'buy',
            'product_key' => $event['key'] ?? '',
            'url' => $event['action_url'] ?? '',
            'image' => $event['image'] ?? 'images/store/reny-store-concert-poster.png',
            'image_url' => $event['image_url'] ?? null,
            'image_alt' => $event['name'] ?? 'Event',
        ];
    });
    $cmsProductSlots = $isShowsPage
        ? collect()
        : collect($publicCms['products'] ?? [])->map(function (array $product) use ($moneyLabel): array {
            return [
                'key' => 'cms-product-'.($product['key'] ?? ''),
                'kind' => $product['category'] ?? 'product',
                'title' => $product['name'] ?? 'Product',
                'eyebrow' => $product['type'] ?? 'Product',
                'description' => $product['summary'] ?? '',
                'price_label' => ($product['mode'] ?? 'buy') === 'buy'
                    ? $moneyLabel((int) ($product['amount_cents'] ?? 0), (string) ($product['currency'] ?? 'USD')).($product['suffix'] ?? '')
                    : '',
                'cta_label' => $product['cta'] ?? 'BUY NOW',
                'action_type' => $product['mode'] ?? 'buy',
                'product_key' => $product['key'] ?? '',
                'url' => $product['action_url'] ?? '',
                'image' => $product['image'] ?? 'images/store/work-in-progress.png',
                'image_url' => $product['image_url'] ?? null,
                'image_alt' => $product['name'] ?? 'Product',
            ];
        });
    $representedProductKeys = $baseStorefrontSlots->pluck('product_key')->filter();
    $storefrontSlots = $baseStorefrontSlots
        ->concat($cmsEventSlots)
        ->concat($cmsProductSlots)
        ->reject(fn (array $slot, int $index): bool => $index >= $baseStorefrontSlots->count()
            && $representedProductKeys->contains($slot['product_key'] ?? null))
        ->reject(fn (array $slot): bool => ($slot['product_key'] ?? null) === 'concert'
            || str((string) ($slot['title'] ?? ''))->squish()->lower()->toString() === 'reny renteria en concierto')
        ->unique(fn (array $slot): string => (string) ($slot['product_key'] ?? $slot['key'] ?? ''))
        ->values();
    $rsvpTickets = $rsvpTickets ?? [];
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
    $slotCountdownTarget = function (?string $value, ?string $timezone = null) use ($storeTimezone) {
        if (! filled($value)) {
            return null;
        }

        try {
            return \Carbon\CarbonImmutable::parse($value, filled($timezone) ? $timezone : $storeTimezone);
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

        @include('partials.public-seo', ['seo' => $pageSettings, 'fallbackTitle' => ($isShowsPage ? 'Shows' : 'Store').' | Reny Renteria'])

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="golden-stage-page checkout-page store-stage-page" data-analytics-screen="{{ $isShowsPage ? 'shows' : 'store' }}" data-preferred-currency="{{ auth()->user()?->preferred_currency ?? 'USD' }}">
        <div class="store-shell home-shell golden-stage-shell store-stage-shell" data-public-page-root>
            @include('partials.stage-lights')

            @include('partials.cms-preview-banner')
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo-white.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <x-public-navigation :active="$activeNavigation" />
                </div>

                <x-member-card />
            </aside>

            <main class="main-content store-content golden-stage-main store-stage-main" id="{{ $isShowsPage ? 'shows' : 'store' }}">
                <header class="mobile-header golden-stage-mobile-header store-stage-mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                            <img
                                class="brand-logo"
                                src="{{ asset('images/reny-renteria-logo-white.png') }}"
                                alt="Reny Renteria"
                            >
                        </a>
                    </div>
                </header>

                <div class="store-stage-stack">
                    <x-royal-pass-banner :pass="$royalPass" :show-images="false" />

                    @unless ($isShowsPage)
                        <section class="public-page-intro" aria-labelledby="store-page-title">
                            <p>{{ $pageSettings['eyebrow'] ?? 'Official Store' }}</p>
                            <h1 id="store-page-title">{{ $pageSettings['title'] ?? 'Shows and releases' }}</h1>
                            <strong>{{ $pageSettings['subtitle'] ?? 'Tickets, music and limited products' }}</strong>
                            <span>{{ $pageSettings['description'] ?? '' }}</span>
                            @if (filled($pageSettings['cover_url'] ?? null))
                                <img src="{{ $pageSettings['cover_url'] }}" alt="{{ $pageSettings['cover_alt'] ?? '' }}">
                            @endif
                        </section>
                    @endunless

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
                                    ? $slotCountdownTarget($slot['countdown_at'] ?? null, $slot['timezone'] ?? null)
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
                                        @elseif ($slotActionType === 'link')
                                            @if (filled($slot['url'] ?? null))
                                                <a class="store-button store-button-light" href="{{ $slot['url'] }}" target="_blank" rel="noreferrer">{{ $slot['cta_label'] ?? 'OPEN' }}</a>
                                            @else
                                                <span class="store-button store-button-light" aria-disabled="true">Unavailable</span>
                                            @endif
                                        @else
                                            <button
                                                class="store-button store-button-light"
                                                type="button"
                                                data-buy="{{ $slotProductKey }}"
                                                data-buy-name="{{ $slot['title'] }}"
                                                data-buy-type="{{ $slotType($slot) }}"
                                                data-buy-summary="{{ str_replace("\n", ' - ', $slot['description'] ?? '') }}"
                                                data-buy-image="{{ $slotImage($slot) }}"
                                                @if ($slotPriceValue > 0) data-buy-price-value="{{ number_format($slotPriceValue, 2, '.', '') }}" @endif
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
                </div>

                <x-public-navigation :active="$activeNavigation" mobile />
            </main>
        </div>

        @include('partials.store-checkout-modals')
        @include('partials.music-player-modal')
    </body>
</html>
