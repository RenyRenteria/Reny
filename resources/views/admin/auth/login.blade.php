<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Enter | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="auth-shell admin-auth-shell">
            <section class="auth-panel admin-auth-panel" aria-labelledby="admin-login-title">
                <div class="brand-link" aria-label="Reny Renteria">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </div>

                <h1 id="admin-login-title">Enter</h1>

                @if (session('status'))
                    <div class="auth-status">{{ session('status') }}</div>
                @endif

                <form class="auth-form" method="POST" action="{{ route('admin.login.store') }}">
                    @csrf

                    <label>
                        <span>Email</span>
                        <input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required>
                        @error('email')
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

                    <button class="auth-button" type="submit">Enter</button>
                </form>
            </section>
        </main>
    </body>
</html>
