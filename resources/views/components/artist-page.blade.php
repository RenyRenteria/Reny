@props(['active', 'title', 'description', 'image' => 'images/artist/reny-live-portrait.webp'])
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        @include('partials.google-analytics')
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.public-seo', ['seo' => ['meta_description' => $description, 'og_image' => asset($image)], 'fallbackTitle' => $title.' | Reny Rentería'])
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="reny-site golden-stage-page artist-page" data-analytics-screen="{{ $active }}">
        <div class="store-shell home-shell golden-stage-shell artist-shell" data-public-page-root>
            <x-public-navigation />
            <main class="main-content golden-stage-main artist-main" id="{{ $active }}">
                <span id="site-content" class="sr-only" tabindex="-1"></span>
                <div class="artist-stack">{{ $slot }}</div>
            </main>
            <x-public-footer />
        </div>
        @include('partials.music-player-modal')
    </body>
</html>
