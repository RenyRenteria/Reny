<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Reny Renteria') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="site-shell">
            <header class="site-header" aria-label="Primary navigation">
                <a class="brand-link" href="/" aria-label="Reny Renteria home">
                    <img
                        class="brand-logo"
                        src="{{ asset('images/reny-renteria-logo.png') }}"
                        alt="Reny Renteria"
                    >
                </a>

                <nav class="main-nav" aria-label="Main menu">
                    <a href="#royal-pass">Royal Pass</a>
                    <a href="#store">Store</a>
                    <a href="#community">Community</a>
                </nav>

                <a class="nav-action" href="#join" aria-label="Join the Reny Renteria community">
                    <span class="nav-action__dot"></span>
                </a>
            </header>

            <main class="home-stage">
                <section class="home-intro" aria-label="Reny Renteria home">
                    <p>Royal Pass</p>
                    <h1>Reny Renteria</h1>
                </section>
            </main>
        </div>
    </body>
</html>
