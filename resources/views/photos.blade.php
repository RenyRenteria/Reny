@php
    $photos = [
        [
            'image' => 'capri.jpg',
            'type' => 'Album',
            'tone' => 'travel',
            'title' => 'Capri Heartbreak',
            'caption' => 'Travel photo set from Capri, Porto, and Roma.',
            'size' => 'wide',
        ],
        [
            'image' => 'studio.jpg',
            'type' => 'Single post',
            'tone' => 'studio',
            'title' => 'Recording Places',
            'caption' => 'Studio still for the Places release window.',
            'size' => 'tall',
        ],
        [
            'image' => 'radio.jpg',
            'type' => 'Single post',
            'tone' => 'press',
            'title' => 'Radio Ancon',
            'caption' => 'Press cabin image from the promo run.',
            'size' => 'standard',
        ],
        [
            'image' => 'places.jpg',
            'type' => 'Album',
            'tone' => 'travel',
            'title' => 'Places Europe',
            'caption' => 'Madrid, Barcelona, Paris, and Milan visual archive.',
            'size' => 'tall',
        ],
        [
            'image' => 'tv.jpg',
            'type' => 'Single post',
            'tone' => 'press',
            'title' => 'Tu Manana',
            'caption' => 'TV promo still for the campaign.',
            'size' => 'standard',
        ],
        [
            'image' => 'performance.jpg',
            'type' => 'Single post',
            'tone' => 'stage',
            'title' => 'Performance Frames',
            'caption' => 'Movement and live-stage image reference.',
            'size' => 'wide',
        ],
        [
            'image' => 'rehearsal.jpg',
            'type' => 'Single post',
            'tone' => 'stage',
            'title' => 'Organik Rehearsal',
            'caption' => 'Choreography-focused rehearsal still.',
            'size' => 'standard',
        ],
        [
            'image' => 'cover.jpg',
            'type' => 'Single post',
            'tone' => 'studio',
            'title' => 'Eight Years Later',
            'caption' => 'Cover-session image treatment.',
            'size' => 'tall',
        ],
        [
            'image' => 'campaign.jpg',
            'type' => 'Album',
            'tone' => 'studio',
            'title' => 'Save My Body',
            'caption' => 'Campaign stills and 5D Stage release images.',
            'size' => 'wide',
        ],
        [
            'image' => 'merch.jpg',
            'type' => 'Album',
            'tone' => 'store',
            'title' => 'Merch Drop',
            'caption' => 'Product-facing photography for the Store bridge.',
            'size' => 'standard',
        ],
        [
            'image' => 'dance.jpg',
            'type' => 'Single post',
            'tone' => 'stage',
            'title' => 'Choreo Session',
            'caption' => 'Rehearsal and movement photo treatment.',
            'size' => 'tall',
        ],
        [
            'image' => 'tvVisit.jpg',
            'type' => 'Single post',
            'tone' => 'press',
            'title' => 'Mas23 Visit',
            'caption' => 'Panama press still from the music promo run.',
            'size' => 'standard',
        ],
    ];

    $pageSettings = $publicCms['page'] ?? [];
    $hasCmsPayload = in_array($publicCms['_cms_source'] ?? 'static', ['cms', 'cache', 'preview'], true);

    if ($hasCmsPayload) {
        $photos = $publicCms['photos'] ?? [];
    } elseif (! empty($publicCms['photos'] ?? [])) {
        $photos = $publicCms['photos'];
    }

    $photoGroups = [];

    foreach ($photos as $index => $photo) {
        $albumId = $photo['album_id'] ?? null;

        if ($albumId !== null && $albumId !== '') {
            $groupKey = 'album-' . $albumId;

            if (! isset($photoGroups[$groupKey])) {
                $photoGroups[$groupKey] = [
                    'album_id' => $albumId,
                    'title' => $photo['title'] ?? 'Photo album',
                    'photos' => [],
                ];
            }

            $photoGroups[$groupKey]['photos'][] = $photo;

            continue;
        }

        $photoGroups['photo-' . $index] = [
            'album_id' => null,
            'title' => $photo['title'] ?? 'Photo',
            'photos' => [$photo],
        ];
    }

    $photoGroups = array_values($photoGroups);

    $royalProductKey = 'royal';
    $royalCtaLabel = 'Unlock Royal Pass';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @include('partials.public-seo', ['seo' => $pageSettings, 'fallbackTitle' => 'Photos | Reny Renteria'])

        <meta name="csrf-token" content="{{ csrf_token() }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="photos">
        <div class="photos-shell" data-public-page-root>
            @include('partials.cms-preview-banner')
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <x-public-navigation />
                </div>

                <x-member-card />
            </aside>

            <main class="main-content photos-content" id="photos">
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

                <section class="public-page-intro" aria-labelledby="photos-page-title">
                    <p>{{ $pageSettings['eyebrow'] ?? 'Photos' }}</p>
                    <h1 id="photos-page-title">{{ $pageSettings['title'] ?? 'Visual archive' }}</h1>
                    <strong>{{ $pageSettings['subtitle'] ?? 'Albums and moments' }}</strong>
                    <span>{{ $pageSettings['description'] ?? '' }}</span>
                    @if (filled($pageSettings['cover_url'] ?? null))
                        <img src="{{ $pageSettings['cover_url'] }}" alt="{{ $pageSettings['cover_alt'] ?? '' }}">
                    @endif
                </section>

                <section class="photo-masonry" aria-label="Photos gallery">
                    @foreach ($photoGroups as $group)
                        @if (! empty($group['album_id']) && count($group['photos']) > 1)
                            <article
                                class="photo-album-group"
                                data-photo-album-group="{{ $group['album_id'] }}"
                                data-photo-layout="horizontal-album"
                                aria-label="{{ $group['title'] }}"
                            >
                                <div class="photo-album-strip">
                                    @foreach ($group['photos'] as $photo)
                                        @include('partials.photo-tile', ['photo' => $photo])
                                    @endforeach
                                </div>
                            </article>
                        @else
                            @include('partials.photo-tile', ['photo' => $group['photos'][0]])
                        @endif
                    @endforeach
                </section>

                <x-public-navigation mobile />
            </main>

            <button
                class="sr-only"
                type="button"
                data-photo-paywall-trigger
                data-buy="{{ $royalProductKey }}"
                data-buy-name="Royal Pass"
                data-buy-type="Membership"
                data-buy-summary="Monthly membership with exclusive content, community and more."
                data-buy-image="{{ asset('images/store/royal-pass.png') }}"
                data-buy-url="{{ route('store.checkout', ['product' => $royalProductKey]) }}"
                aria-hidden="true"
                tabindex="-1"
            >{{ $royalCtaLabel }}</button>
        </div>

        <div class="photo-lightbox" id="photoLightbox" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="photoLightboxTitle">
            <div class="photo-lightbox-inner">
                <div class="photo-lightbox-frame">
                    <img id="photoLightboxImage" alt="">
                </div>
                <div class="photo-lightbox-copy">
                    <span id="photoLightboxType">Photo</span>
                    <h2 id="photoLightboxTitle">Photo title</h2>
                    <p id="photoLightboxCaption">Photo caption</p>
                    <button class="photo-lightbox-close" id="photoLightboxClose" type="button">Close</button>
                </div>
            </div>
        </div>
        @include('partials.music-player-modal')
        @include('partials.store-checkout-modals', ['detailPlaceholderImage' => 'images/store/royal-pass.png'])
    </body>
</html>
