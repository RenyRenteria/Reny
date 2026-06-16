<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Recover access | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="auth_forgot_password">
        <main class="auth-shell">
            <section class="auth-panel" aria-labelledby="recover-title">
                <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>

                <p class="auth-kicker">Account recovery</p>
                <h1 id="recover-title">Recover access</h1>
                <p class="auth-copy">Enter your email or phone number. If an account exists, reset instructions will be sent.</p>

                @if (session('status'))
                    <div class="auth-status">{{ session('status') }}</div>
                @endif

                <form class="auth-form" method="POST" action="{{ route('password.email') }}">
                    @csrf

                    <label>
                        <span>Email or phone</span>
                        <input name="identifier" type="text" value="{{ old('identifier') }}" autocomplete="username" required>
                        @error('identifier')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <button class="auth-button" type="submit">Send recovery link</button>
                </form>

                <div class="auth-links">
                    <a href="{{ route('login') }}">Back to sign in</a>
                </div>
            </section>
        </main>
    </body>
</html>
