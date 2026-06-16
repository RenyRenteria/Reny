<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Session expired | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="auth_session_expired">
        <main class="auth-shell">
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
    </body>
</html>
