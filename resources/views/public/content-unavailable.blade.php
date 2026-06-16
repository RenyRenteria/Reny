<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $content->title }} | Archived</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="content_unavailable">
        <main class="public-content-shell">
            <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
            </a>

            <article class="public-content-card">
                <span>ARCHIVED</span>
                <h1>{{ $content->title }}</h1>
                <p>This content is no longer in public rotation, but the reference is still available.</p>
            </article>
        </main>
    </body>
</html>
