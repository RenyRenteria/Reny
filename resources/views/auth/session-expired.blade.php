<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        @include('partials.google-analytics')
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Session expired | Reny Renteria</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="reny-site" data-analytics-screen="auth_session_expired">
        <x-public-navigation />
        <main class="auth-shell">
                <span id="site-content" class="sr-only" tabindex="-1"></span>
            <section class="auth-panel" aria-labelledby="expired-title">
                <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>

                <p class="auth-kicker">Session</p>
                <h1 id="expired-title">Session expired</h1>
                <p class="auth-copy">Sign in again to continue. Premium access is checked again after authentication.</p>

                <a class="auth-button auth-button-link" href="{{ route('login') }}">Sign in again</a>
            </section>
        </main>
            <x-public-footer />
    </body>
</html>
