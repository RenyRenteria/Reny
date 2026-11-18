<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Reset password | Reny Renteria</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="reny-site" data-analytics-screen="auth_reset_password">
        <x-public-navigation />
        <main class="auth-shell">
                <span id="site-content" class="sr-only" tabindex="-1"></span>
            <section class="auth-panel" aria-labelledby="reset-title">
                <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>

                <p class="auth-kicker">Account recovery</p>
                <h1 id="reset-title">Choose a new password</h1>
                <p class="auth-copy">Enter and confirm the new password for your account.</p>

                <form class="auth-form" method="POST" action="{{ route('password.store') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="email" value="{{ old('email', $email) }}">

                    @error('email')
                        <small>{{ $message }}</small>
                    @enderror

                    <label>
                        <span>New password</span>
                        <input name="password" type="password" autocomplete="new-password" required>
                        @error('password')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label>
                        <span>Confirm new password</span>
                        <input name="password_confirmation" type="password" autocomplete="new-password" required>
                    </label>

                    <button class="auth-button" type="submit">Reset password</button>
                </form>

                <div class="auth-links">
                    <a href="{{ route('login') }}">Back to sign in</a>
                </div>
            </section>
        </main>
            <x-public-footer />
    </body>
</html>
