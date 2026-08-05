<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Sign in | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="auth_login">
        <main class="auth-shell">
            <section class="auth-panel" aria-labelledby="login-title">
                <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>

                <p class="auth-kicker">Royal Pass</p>
                <h1 id="login-title">Sign in</h1>
                <p class="auth-copy">Use your email or phone number to continue into your account.</p>

                @if (session('status'))
                    <div class="auth-status" role="status">{{ session('status') }}</div>
                @endif

                <form class="auth-form" method="POST" action="{{ route('login.store') }}">
                    @csrf

                    <label>
                        <span>Email or phone</span>
                        <input name="identifier" type="text" value="{{ old('identifier') }}" autocomplete="username" required>
                        @error('identifier')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label>
                        <span>Password</span>
                        <input name="password" type="password" autocomplete="current-password" required>
                        @error('password')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="auth-check">
                        <input name="remember" type="checkbox" value="1">
                        <span>Keep me signed in</span>
                    </label>

                    <button class="auth-button" type="submit">Sign in</button>
                </form>

                <div class="auth-links">
                    <a href="{{ route('password.request') }}">Forgot password?</a>
                    <a href="{{ route('register') }}">Create account</a>
                </div>
            </section>
        </main>
    </body>
</html>
