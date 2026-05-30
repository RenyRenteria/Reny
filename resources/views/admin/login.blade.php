<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin Login | {{ config('app.name', 'Reny') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="admin-body">
        <main class="admin-login-shell">
            <section class="admin-login-card">
                <img src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria" class="admin-login-logo">
                <h1>Content admin</h1>
                <p>Sign in to update the music content shown on the site.</p>

                <form method="POST" action="{{ route('admin.login.store') }}" class="admin-form">
                    @csrf

                    <label>
                        Password
                        <input type="password" name="password" autocomplete="current-password" required autofocus>
                    </label>

                    @error('password')
                        <p class="admin-error">{{ $message }}</p>
                    @enderror

                    <button class="admin-primary-button" type="submit">Sign in</button>
                </form>
            </section>
        </main>
    </body>
</html>
