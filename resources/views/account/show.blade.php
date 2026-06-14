<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Account | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="auth-shell account-shell">
            <section class="auth-panel account-panel" aria-labelledby="account-title">
                <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>

                <p class="auth-kicker">User hub</p>
                <h1 id="account-title">{{ $user->name }}</h1>
                <p class="auth-copy">Access state: {{ str_replace('_', ' ', $accessState) }}.</p>

                @if ($user->hasRoyalAccess())
                    <div class="account-state account-state-active">
                        <strong>Royal Pass active</strong>
                        <span>Premium access remains available while the pass is active.</span>
                    </div>
                @else
                    <div class="account-state">
                        <strong>Open mode</strong>
                        <span>Reactivate Royal Pass to unlock premium music, community actions and member drops.</span>
                    </div>
                    <a class="auth-button auth-button-link" href="{{ route('store') }}">Get your Royal Pass</a>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="auth-secondary-button" type="submit">Log out</button>
                </form>
            </section>
        </main>
    </body>
</html>
