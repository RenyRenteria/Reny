<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Royal Content | Reny Renteria</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="royal_content">
        <main class="auth-shell">
            <section class="auth-panel" aria-labelledby="royal-content-title">
                <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>
                <p class="auth-kicker">Royal content</p>
                <h1 id="royal-content-title">{{ str_replace('-', ' ', $resource) }}</h1>
                <p class="auth-copy">{{ $secureStreamToken }}</p>
            </section>
        </main>
    </body>
</html>
