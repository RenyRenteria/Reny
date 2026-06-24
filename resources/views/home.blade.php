@php
    $defaultVideo = [
        'id' => 'Ue8orNrHw9s',
        'title' => 'Reny Renteria - I Swear (Official Music Video)',
        'meta' => 'Featured YouTube premiere',
        'external_url' => 'https://www.youtube.com/watch?v=Ue8orNrHw9s',
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
    $singles = collect($publicCms['singles'] ?? [])->take(3)->values();
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
    $albumProductKey = $album['product_key'] ?? 'deluxe';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Reny Renteria') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="home">
        <div class="store-shell home-shell" data-public-page-root>
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
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
                        <a class="tab" href="{{ route('videos') }}">
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
                        <a class="tab" href="{{ route('store') }}">
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

            <main class="main-content store-content home-content" id="home">
                <header class="mobile-header home-mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                            <img
                                class="brand-logo"
                                src="{{ asset('images/reny-renteria-logo.png') }}"
                                alt="Reny Renteria"
                            >
                        </a>
                    </div>
                </header>

                <div class="home-stack">
                    <section class="home-royal-pass" aria-label="Royal Pass">
                        <div class="home-royal-pass-copy">
                            <span>{{ $royalPass['copy_before'] ?? 'Get your' }} <strong>{{ $royalPass['emphasis'] ?? 'Royal Pass' }}</strong></span>
                            <p>{{ $royalPass['copy_after'] ?? 'Unlock community, exclusive content and more...' }}</p>
                        </div>
                        <button
                            class="store-button home-unlock-button"
                            type="button"
                            data-buy="{{ $royalPass['product_key'] ?? 'royal' }}"
                            data-buy-name="{{ $royalPass['emphasis'] ?? 'Royal Pass' }}"
                            data-buy-type="Membership"
                            data-buy-summary="Monthly membership with exclusive content, community and more."
                            data-buy-image="{{ asset('images/store/crown-collection.png') }}"
                            data-buy-url="{{ route('store.checkout', ['product' => $royalPass['product_key'] ?? 'royal']) }}"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M7 17 17 7"></path>
                                <path d="M8 7h9v9"></path>
                            </svg>
                            Unlock
                        </button>
                        <div class="home-royal-pass-images" aria-hidden="true">
                            @foreach ($royalImages as $image)
                                <img src="{{ $image }}" alt="">
                            @endforeach
                        </div>
                    </section>

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
                                    src="https://www.youtube.com/embed/{{ $featuredVideo['id'] }}"
                                    title="{{ $featuredVideo['title'] }}"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
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
                                    $eventStatusId = 'home-rsvp-status-' . \Illuminate\Support\Str::slug($eventKey);
                                    $rsvpTicket = $rsvpTickets[$eventKey] ?? null;
                                @endphp
                                <article class="home-show-card">
                                    <img class="home-show-image" src="{{ $slotImage($event) }}" alt="{{ $event['image_alt'] ?? $event['title'] }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">
                                    <div class="home-show-copy">
                                        <h2>{{ $event['title'] }}</h2>
                                        @foreach ($eventLinesForSlot as $line)
                                            <p>{{ $line }}</p>
                                        @endforeach
                                        @if (filled($eventVisiblePrice))
                                            <p>{{ $eventVisiblePrice }}</p>
                                        @endif

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
                                        @elseif ($eventAction === 'link' && filled($event['url'] ?? null))
                                            <a class="home-pill-button" href="{{ $event['url'] }}" target="_blank" rel="noreferrer">{{ $event['cta_label'] ?? 'GET TICKETS' }}</a>
                                        @else
                                            <button
                                                class="home-pill-button"
                                                type="button"
                                                data-buy="{{ $eventKey }}"
                                                data-buy-name="{{ $event['title'] }}"
                                                data-buy-type="Event"
                                                data-buy-summary="{{ str_replace("\n", ' - ', $event['description'] ?? '') }}"
                                                data-buy-image="{{ $slotImage($event) }}"
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
                                @if ($album)
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
                                    @if ($album)
                                        <button
                                            class="home-play-here"
                                            type="button"
                                            @include('partials.music-play-trigger-attributes', ['item' => $album, 'type' => 'album', 'label' => "Play {$albumTitle}"])
                                        >Play Here</button>
                                    @else
                                        <a class="home-play-here" href="{{ route('music') }}">Play Here</a>
                                    @endif
                                    <button
                                        class="home-buy-deluxe"
                                        type="button"
                                        data-buy="{{ $albumProductKey }}"
                                        data-buy-name="{{ $albumTitle }}"
                                        data-buy-type="Album"
                                        data-buy-summary="{{ $album['summary'] ?? 'Deluxe album checkout' }}"
                                        data-buy-image="{{ $albumImage }}"
                                        data-buy-url="{{ route('store.checkout', ['product' => $albumProductKey]) }}"
                                    >Buy Deluxe</button>
                                </div>
                            </article>

                            <section class="home-singles" aria-labelledby="home-singles-title">
                                <h2 id="home-singles-title">Latest Singles</h2>
                                <div class="home-single-list">
                                    @foreach ($singles as $single)
                                        @include('partials.music-single-card', ['single' => $single])
                                    @endforeach
                                </div>
                            </section>
                        </div>
                    </section>
                </div>

                <nav class="mobile-bottom-nav home-bottom-nav" aria-label="Mobile menu">
                    <a href="{{ route('music') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M9 18V5l10-2v13"></path>
                            <circle cx="7" cy="18" r="3"></circle>
                            <circle cx="17" cy="16" r="3"></circle>
                        </svg>
                        <span class="sr-only">MUSIC</span>
                    </a>
                    <a href="{{ route('videos') }}">
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
                    <a href="{{ route('store') }}">
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
        @include('partials.music-player-modal')
    </body>
</html>
