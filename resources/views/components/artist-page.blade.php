@props(['active', 'title', 'description', 'image' => 'images/artist/reny-live-portrait.webp'])
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.public-seo', ['seo' => ['meta_description' => $description, 'og_image' => asset($image)], 'fallbackTitle' => $title.' | Reny Rentería'])
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="golden-stage-page artist-page" data-analytics-screen="{{ $active }}">
        <div class="store-shell home-shell golden-stage-shell artist-shell" data-public-page-root>
            @include('partials.stage-lights')
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                        <img class="brand-logo" src="{{ asset('images/reny-renteria-logo-white.png') }}" alt="Reny Renteria">
                    </a>
                    <x-public-navigation :active="$active" />
                </div>
                <x-member-card />
            </aside>
            <main class="main-content golden-stage-main artist-main" id="{{ $active }}">
                <header class="mobile-header golden-stage-mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                            <img class="brand-logo" src="{{ asset('images/reny-renteria-logo-white.png') }}" alt="Reny Renteria">
                        </a>
                    </div>
                </header>
                <div class="artist-stack">{{ $slot }}</div>
                <x-public-navigation :active="$active" mobile />
            </main>
        </div>
        @include('partials.music-player-modal')
    </body>
</html>
