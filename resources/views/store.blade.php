@php
    $storefront = $publicCms['storefront'] ?? app(\App\Services\StorefrontSettingsService::class)->publicPayload();
    $royalPass = $storefront['royal_pass'] ?? [];
    $storefrontSlots = collect(['event_primary', 'event_secondary', 'album', 'merch'])
        ->map(fn (string $key): array => data_get($storefront, "slots.{$key}", []))
        ->filter()
        ->values();
    $rsvpTickets = $rsvpTickets ?? [];
    $isGuestPreview = (bool) request()->attributes->get('site_editor_guest_preview', false);
    $shouldShowRoyalPass = $isGuestPreview || auth()->guest();
    $slotImage = fn (array $slot): string => $slot['image_url'] ?? asset($slot['image'] ?? 'images/store/work-in-progress.png');
    $slotType = fn (array $slot): string => $slot['eyebrow'] ?: str($slot['kind'] ?? 'product')->headline()->toString();
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

        <title>Store | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;500&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="store">
        <div class="store-shell">
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <nav class="tabs" aria-label="Main menu">
                        <a class="tab" href="{{ route('music') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M9 18V5l10-2v13"></path>
                                <circle cx="7" cy="18" r="3"></circle>
                                <circle cx="17" cy="16" r="3"></circle>
                            </svg>
                            <span>MUSIC</span>
                        </a>
                        <a class="tab" href="{{ url('/videos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="m22 8-6 4 6 4V8Z"></path>
                                <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                            </svg>
                            <span>VIDEOS</span>
                        </a>
                        <a class="tab" href="{{ url('/photos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <path d="m21 15-5-5L5 21"></path>
                            </svg>
                            <span>PHOTOS</span>
                        </a>
                        <a class="tab" href="{{ url('/community') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                            </svg>
                            <span>COMMUNITY</span>
                        </a>
                        <a class="tab is-active" href="{{ url('/store') }}" aria-current="page">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M4 10h16"></path>
                                <path d="M5 10l1.5-5h11L19 10"></path>
                                <path d="M6 10v9h12v-9"></path>
                                <path d="M9 19v-5h6v5"></path>
                            </svg>
                            <span>STORE</span>
                        </a>
                    </nav>
                </div>

                <x-member-card />
            </aside>

            <main class="main-content store-content" id="store">
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

                @if ($shouldShowRoyalPass)
                    <section class="store-royal-pass" aria-label="Royal Pass">
                        <p>
                            {{ $royalPass['copy_before'] ?? 'Get your' }}
                            <strong>{{ $royalPass['emphasis'] ?? 'Royal Pass' }}</strong>
                            {{ $royalPass['copy_after'] ?? 'to unlock exclusive content, community and more' }}
                        </p>
                        <button
                            class="store-button store-royal-pass-button"
                            type="button"
                            data-buy="{{ $royalPass['product_key'] ?? 'royal' }}"
                            data-buy-name="{{ $royalPass['emphasis'] ?? 'Royal Pass' }}"
                            data-buy-type="Membership"
                            data-buy-summary="Monthly membership with exclusive content, community and more."
                            data-buy-image="{{ asset('images/store/crown-collection.png') }}"
                            data-buy-url="{{ route('store.checkout', ['product' => $royalPass['product_key'] ?? 'royal']) }}"
                        >{{ $royalPass['cta_label'] ?? 'BUY HERE' }}</button>
                    </section>
                @endif

                <section class="storefront" aria-label="Store products">
                    <div class="storefront-grid">
                        @foreach ($storefrontSlots as $slot)
                            @php
                                $slotKey = $slot['key'] ?? 'slot-'.$loop->index;
                                $slotProductKey = $slot['product_key'] ?? $slotKey;
                                $slotActionType = $slot['action_type'] ?? 'buy';
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
                                    @if (filled($slot['price_label'] ?? null))
                                        <strong class="storefront-price">{{ $slot['price_label'] }}</strong>
                                    @endif

                                    <div class="storefront-action-row">
                                        @if ($slotActionType === 'rsvp')
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

                                    @if ($slotActionType === 'rsvp')
                                        <p
                                            class="storefront-rsvp-status sr-only {{ $rsvpTicket ? 'is-confirmed' : '' }}"
                                            id="{{ $slotStatusId }}"
                                        >
                                            @if ($rsvpTicket)
                                                Reserved - {{ str_replace('_', ' ', $rsvpTicket['status']) }} - Code {{ $rsvpTicket['code'] }}
                                            @else
                                                Free RSVP confirms a reservation on this account.
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <nav class="mobile-bottom-nav" aria-label="Mobile menu">
                    <a href="{{ route('music') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M9 18V5l10-2v13"></path>
                            <circle cx="7" cy="18" r="3"></circle>
                            <circle cx="17" cy="16" r="3"></circle>
                        </svg>
                        <span class="sr-only">MUSIC</span>
                    </a>
                    <a href="{{ url('/videos') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="m22 8-6 4 6 4V8Z"></path>
                            <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                        </svg>
                        <span class="sr-only">VIDEOS</span>
                    </a>
                    <a href="{{ url('/photos') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <path d="m21 15-5-5L5 21"></path>
                        </svg>
                        <span class="sr-only">PHOTOS</span>
                    </a>
                    <a href="{{ url('/community') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                        </svg>
                        <span class="sr-only">COMMUNITY</span>
                    </a>
                    <a class="is-active" href="{{ url('/store') }}" aria-current="page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M4 10h16"></path>
                            <path d="M5 10l1.5-5h11L19 10"></path>
                            <path d="M6 10v9h12v-9"></path>
                            <path d="M9 19v-5h6v5"></path>
                        </svg>
                        <span class="sr-only">STORE</span>
                    </a>
                </nav>
            </main>
        </div>

        @include('partials.store-checkout-modals')
    </body>
</html>
