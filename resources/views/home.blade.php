@php
    $defaultVideo = [
        'id' => 'UWDLtZCoTag',
        'title' => 'Reny Renteria - Take a bite (Official Music Video)',
        'meta' => 'Featured YouTube premiere',
        'external_url' => 'https://www.youtube.com/watch?v=UWDLtZCoTag',
    ];
    $featuredVideo = array_filter($publicCms['featured_video'] ?? []) ? $publicCms['featured_video'] : $defaultVideo;
    $featuredVideo['external_url'] = $featuredVideo['external_url']
        ?? (! empty($featuredVideo['id']) ? "https://www.youtube.com/watch?v={$featuredVideo['id']}" : null);
    $featuredVideo['id'] = $featuredVideo['id'] ?? $defaultVideo['id'];
    $featuredVideo['title'] = $featuredVideo['title'] ?? $defaultVideo['title'];
    $featuredVideo['meta'] = $featuredVideo['meta'] ?? $defaultVideo['meta'];

    $storefrontSettings = app(\App\Services\StorefrontSettingsService::class);
    $storefront = $publicCms['storefront'] ?? null;

    if (! is_array($storefront)) {
        try {
            $storefront = $storefrontSettings->publicPayload();
        } catch (\Throwable) {
            $storefront = $storefrontSettings->defaults();
        }
    }

    $storefrontEvents = collect(['event_primary', 'event_secondary'])
        ->map(fn (string $key): array => data_get($storefront, "slots.{$key}", []))
        ->filter(fn (array $event): bool => filled($event['title'] ?? null))
        ->values();
    $payloadEvents = collect($publicCms['events'] ?? [])
        ->filter(fn (mixed $event): bool => is_array($event) && filled($event['title'] ?? null))
        ->values();
    $events = $storefrontEvents->isNotEmpty() ? $storefrontEvents : $payloadEvents;

    if ($events->isEmpty()) {
        $storefrontFallback = app(\App\Services\StorefrontSettingsService::class)->defaults();
        $events = collect(['event_primary', 'event_secondary'])
            ->map(fn (string $key): array => data_get($storefrontFallback, "slots.{$key}", []))
            ->filter(fn (array $event): bool => filled($event['title'] ?? null))
            ->values();
    }
    $album = $publicCms['album'] ?? null;
    $royalPass = $publicCms['royal_pass'] ?? [];
    $rsvpTickets = $rsvpTickets ?? [];
    $slotImage = fn (array $slot): string => $slot['image_url'] ?? asset($slot['image'] ?? 'images/store/work-in-progress.png');
    $eventLines = fn (array $event): array => array_values(array_filter(
        preg_split('/\r\n|\r|\n/', (string) ($event['description'] ?? '')),
        fn (string $line): bool => trim($line) !== '',
    ));
    $priceLabel = function (array $event): string {
        $price = trim((string) ($event['price_label'] ?? ''));

        return strcasecmp($price, 'free') === 0 ? '$ FREE' : $price;
    };
    $isFreeEventPrice = function (string $price): bool {
        if (preg_match('/(^|[^a-z])free([^a-z]|$)/i', $price) === 1) {
            return true;
        }

        $numeric = preg_replace('/[^0-9.]/', '', $price);

        return in_array($numeric, ['0', '0.0', '0.00'], true);
    };
    $eventTimezone = config('admin.publishing_timezone', config('app.timezone', 'UTC'));
    $eventCountdownTarget = function (?string $value, ?string $timezone = null) use ($eventTimezone): ?\Carbon\CarbonImmutable {
        if (! filled($value)) {
            return null;
        }

        try {
            return \Carbon\CarbonImmutable::parse($value, filled($timezone) ? $timezone : $eventTimezone);
        } catch (\Throwable) {
            return null;
        }
    };
    $eventCountdownParts = function (\Carbon\CarbonImmutable $target): array {
        $secondsRemaining = (int) max(0, now()->diffInSeconds($target, false));

        return [
            'days' => intdiv($secondsRemaining, 86400),
            'hours' => intdiv($secondsRemaining % 86400, 3600),
            'minutes' => intdiv($secondsRemaining % 3600, 60),
            'seconds' => $secondsRemaining % 60,
        ];
    };
    $royalImages = collect($publicCms['royal_visuals'] ?? [])
        ->merge([
            asset('images/photos/capri.jpg'),
            asset('images/photos/performance.jpg'),
            asset('images/photos/tvVisit.jpg'),
        ])
        ->filter()
        ->take(3)
        ->values();
    $albumImage = $album
        ? ($album['image_url'] ?? (filled($album['store_image'] ?? null) ? asset($album['store_image']) : asset('images/store/work-in-progress.png')))
        : asset('images/store/work-in-progress.png');
    $albumTitle = $album['title'] ?? 'Work in Progress';
    $albumMeta = $album['meta'] ?? '27 tracks';
    $albumIsStorefrontSlot = (bool) ($album['_storefront_slot'] ?? false);
    $albumPriceValue = (float) preg_replace('/[^0-9.]/', '', (string) ($album['price_label'] ?? ''));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.public-seo', ['seo' => [], 'fallbackTitle' => 'Reny Renteria'])

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="home-page" data-analytics-screen="home" data-preferred-currency="{{ auth()->user()?->preferred_currency ?? 'USD' }}">
        <div class="store-shell home-shell" data-public-page-root>
            <div class="stage-lights" aria-hidden="true">
                <span class="stage-light stage-light--one"></span>
                <span class="stage-light stage-light--two"></span>
                <span class="stage-light stage-light--three"></span>
                <span class="stage-light-fixture stage-light-fixture--one"></span>
                <span class="stage-light-fixture stage-light-fixture--two"></span>
                <span class="stage-light-fixture stage-light-fixture--three"></span>
            </div>

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

                    <x-public-navigation />
                </div>

                <x-member-card />
            </aside>

            <main class="main-content store-content home-content" id="home">
                <header class="mobile-header home-mobile-header">
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

                <div class="home-stack">
                    <x-royal-pass-banner :pass="$royalPass" :images="$royalImages" />

                    <section class="video-hero home-video-hero" aria-label="Featured video">
                        <div class="hero-content">
                            <p class="eyebrow">Video<br>Premiere</p>
                            <h1>Watch<br>Now</h1>
                            <h2>Official Music Videos</h2>
                            <p class="hero-copy">
                                A YouTube-first video hub for Reny Renteria with official releases,
                                live performances, playlists, behind the scenes clips, and vlogs in one place.
                            </p>
                            <p class="hero-link">Streaming from<br>youtube.com/renyrenteriam</p>
                        </div>

                        <div class="featured-video">
                            <div class="video-frame">
                                <iframe
                                    src="https://www.youtube.com/embed/{{ $featuredVideo['id'] }}?autoplay=1&amp;mute=1&amp;playsinline=1&amp;rel=0"
                                    title="{{ $featuredVideo['title'] }}"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    loading="eager"
                                    referrerpolicy="strict-origin-when-cross-origin"
                                    allowfullscreen>
                                </iframe>
                            </div>
                            <div class="featured-meta">
                                <div>
                                    <strong>{{ $featuredVideo['title'] }}</strong>
                                    <span>{{ $featuredVideo['meta'] }}</span>
                                </div>
                                @if (! empty($featuredVideo['external_url']))
                                    <a
                                        class="youtube-pill"
                                        href="{{ $featuredVideo['external_url'] }}"
                                        target="_blank"
                                        rel="noreferrer"
                                        data-analytics-type="video"
                                        data-analytics-label="{{ $featuredVideo['title'] }}"
                                    >Open YouTube</a>
                                @endif
                            </div>
                            <a class="home-watch-more" href="{{ route('videos') }}">Watch more</a>
                        </div>
                    </section>

                    <section class="home-shows" aria-labelledby="home-shows-title">
                        <h1 id="home-shows-title">Upcoming Shows</h1>
                        <div class="home-show-list">
                            @foreach ($events as $event)
                                @php
                                    $eventLinesForSlot = $eventLines($event);
                                    $eventKey = $event['product_key'] ?? $event['key'] ?? 'event-'.$loop->index;
                                    $eventAction = $event['action_type'] ?? 'buy';
                                    $eventVisiblePrice = $priceLabel($event);
                                    $isFreeLeadEvent = $isFreeEventPrice($eventVisiblePrice);
                                    $eventPriceValue = (float) preg_replace('/[^0-9.]/', '', $eventVisiblePrice);
                                    $eventHasExchangeablePrice = filled($eventVisiblePrice) && ! $isFreeLeadEvent && $eventPriceValue > 0;
                                    $eventStatusId = 'home-rsvp-status-' . \Illuminate\Support\Str::slug($eventKey);
                                    $rsvpTicket = $rsvpTickets[$eventKey] ?? null;
                                    $countdownTarget = $eventCountdownTarget(
                                        $event['countdown_at'] ?? $event['starts_at'] ?? null,
                                        $event['timezone'] ?? null,
                                    );
                                    $countdownParts = $countdownTarget ? $eventCountdownParts($countdownTarget) : null;
                                @endphp
                                <article class="home-show-card">
                                    <img class="home-show-image" src="{{ $slotImage($event) }}" alt="{{ $event['image_alt'] ?? $event['title'] }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">
                                    <div class="home-show-copy">
                                        <h2>{{ $event['title'] }}</h2>
                                        @foreach ($eventLinesForSlot as $line)
                                            <p>{{ $line }}</p>
                                        @endforeach
                                        @if (filled($eventVisiblePrice))
                                            <p
                                                @if ($eventHasExchangeablePrice)
                                                    data-price="{{ $eventKey }}"
                                                    data-price-value="{{ number_format($eventPriceValue, 2, '.', '') }}"
                                                @endif
                                                >{{ $eventVisiblePrice }}</p>
                                        @endif
                                    </div>

                                    @if ($countdownTarget && $countdownParts)
                                        <div
                                            class="home-event-countdown"
                                            data-countdown-at="{{ $countdownTarget->toIso8601String() }}"
                                            data-countdown-display="segments"
                                            data-countdown-running-label="Show starts in"
                                            data-countdown-ended-label="Event started"
                                            role="timer"
                                            aria-label="Show starts in {{ $countdownParts['days'] }} days, {{ $countdownParts['hours'] }} hours, {{ $countdownParts['minutes'] }} minutes, and {{ $countdownParts['seconds'] }} seconds"
                                        >
                                            <span class="home-event-countdown-kicker" data-countdown-status>Show starts in</span>
                                            <div class="home-event-countdown-grid" aria-hidden="true">
                                                @foreach ([
                                                    'days' => 'Days',
                                                    'hours' => 'Hours',
                                                    'minutes' => 'Minutes',
                                                    'seconds' => 'Seconds',
                                                ] as $unit => $label)
                                                    <span class="home-event-countdown-part">
                                                        <strong data-countdown-unit="{{ $unit }}">{{ str_pad((string) $countdownParts[$unit], 2, '0', STR_PAD_LEFT) }}</strong>
                                                        <span>{{ $label }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="home-show-actions">
                                        @if ($isFreeLeadEvent)
                                            <button
                                                class="home-pill-button"
                                                type="button"
                                                data-free-event-rsvp="{{ $eventKey }}"
                                                data-free-event-name="{{ $event['title'] }}"
                                                data-free-event-price="{{ $eventVisiblePrice }}"
                                                data-free-event-rsvp-endpoint="{{ route('community.free-event-rsvp.store') }}"
                                            >{{ $event['cta_label'] ?? 'GET TICKETS' }}</button>
                                        @elseif ($eventAction === 'rsvp')
                                            <button
                                                class="home-pill-button"
                                                type="button"
                                                data-rsvp="{{ $eventKey }}"
                                                data-rsvp-name="{{ $event['title'] }}"
                                                data-rsvp-endpoint="{{ route('store.rsvp') }}"
                                                data-rsvp-status-target="{{ $eventStatusId }}"
                                                data-rsvp-confirmed="{{ $rsvpTicket ? 'true' : 'false' }}"
                                                aria-describedby="{{ $eventStatusId }}"
                                            >{{ $rsvpTicket ? 'RSVP confirmed' : ($event['cta_label'] ?? 'GET TICKETS') }}</button>
                                        @elseif ($eventAction === 'link')
                                            @if (filled($event['url'] ?? null))
                                                <a class="home-pill-button" href="{{ $event['url'] }}" target="_blank" rel="noreferrer">{{ $event['cta_label'] ?? 'GET TICKETS' }}</a>
                                            @else
                                                <span class="home-pill-button" aria-disabled="true">Unavailable</span>
                                            @endif
                                        @else
                                            <button
                                                class="home-pill-button"
                                                type="button"
                                                data-buy="{{ $eventKey }}"
                                                data-buy-name="{{ $event['title'] }}"
                                                data-buy-type="Event"
                                                data-buy-summary="{{ str_replace("\n", ' - ', $event['description'] ?? '') }}"
                                                data-buy-image="{{ $slotImage($event) }}"
                                                @if ($eventHasExchangeablePrice) data-buy-price-value="{{ number_format($eventPriceValue, 2, '.', '') }}" @endif
                                                data-buy-url="{{ route('store.checkout', ['product' => $eventKey]) }}"
                                            >{{ $event['cta_label'] ?? 'GET TICKETS' }}</button>
                                        @endif

                                        @if ($eventAction === 'rsvp' && ! $isFreeLeadEvent)
                                            <p class="storefront-rsvp-status sr-only {{ $rsvpTicket ? 'is-confirmed' : '' }}" id="{{ $eventStatusId }}">
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

                    <section class="home-music" aria-labelledby="home-music-title">
                        <h1 id="home-music-title">Music</h1>
                        <div class="home-music-layout">
                            <article class="home-album-card">
                                <div class="home-album-heading">
                                    <span>Latest Album</span>
                                    <h2>{{ $albumTitle }}</h2>
                                    <p>Album &bull; {{ $albumMeta }}</p>
                                </div>
                                @if ($album && ! $albumIsStorefrontSlot)
                                    <button
                                        class="home-album-cover-button"
                                        type="button"
                                        @include('partials.music-play-trigger-attributes', ['item' => $album, 'type' => 'album', 'label' => "Play {$albumTitle} cover"])
                                    >
                                        <img class="home-album-image" src="{{ $albumImage }}" alt="{{ $album['image_alt'] ?? $albumTitle }}" loading="lazy" decoding="async">
                                    </button>
                                @else
                                    <img class="home-album-image" src="{{ $albumImage }}" alt="{{ $albumTitle }}" loading="lazy" decoding="async">
                                @endif
                                <div class="home-album-actions">
                                    @if ($albumIsStorefrontSlot && ($album['action_type'] ?? null) === 'buy')
                                        <button
                                            class="home-play-here"
                                            type="button"
                                            data-buy="{{ $album['product_key'] }}"
                                            data-buy-name="{{ $albumTitle }}"
                                            data-buy-type="Album"
                                            data-buy-summary="{{ $album['summary'] ?? '' }}"
                                            data-buy-image="{{ $albumImage }}"
                                            @if ($albumPriceValue > 0) data-buy-price-value="{{ number_format($albumPriceValue, 2, '.', '') }}" @endif
                                            data-buy-url="{{ route('store.checkout', ['product' => $album['product_key']]) }}"
                                        >{{ $album['cta_label'] ?? 'BUY NOW' }}</button>
                                    @elseif ($albumIsStorefrontSlot)
                                        <a class="home-play-here" href="{{ $album['url'] ?? route('music') }}">{{ $album['cta_label'] ?? 'LISTEN' }}</a>
                                    @elseif ($album)
                                        <button
                                            class="home-play-here"
                                            type="button"
                                            @include('partials.music-play-trigger-attributes', ['item' => $album, 'type' => 'album', 'label' => "Play {$albumTitle}"])
                                        >Play Here</button>
                                    @else
                                        <a class="home-play-here" href="{{ route('music') }}">Play Here</a>
                                    @endif
                                </div>
                            </article>

                        </div>
                    </section>
                </div>

                <x-public-navigation mobile extra-class="home-bottom-nav" />
            </main>
        </div>

        @include('partials.store-checkout-modals')
        @include('partials.music-player-modal')
    </body>
</html>
