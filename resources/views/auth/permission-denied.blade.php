<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        @include('partials.google-analytics')
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} | Reny Renteria</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="reny-site" data-analytics-screen="permission_denied" data-access-state="{{ $state['state'] }}">
        <x-public-navigation />
        <main class="auth-shell">
                <span id="site-content" class="sr-only" tabindex="-1"></span>
            <section class="auth-panel access-gate permission-panel" data-section="{{ $section }}" aria-labelledby="permission-title">
                <a class="brand-link brand-link-centered" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>

                <div>
                    <span>{{ strtoupper($section) }} ACCESS</span>
                    <h1 id="permission-title">{{ $title }}</h1>
                    <p>{{ $message }}</p>
                </div>

                <dl class="account-state-details">
                    <div>
                        <dt>Account state</dt>
                        <dd>{{ $state['badge'] }}</dd>
                    </div>
                </dl>

                @if ($state['action_label'] && $state['action_url'])
                    <a
                        class="access-gate-button"
                        href="{{ $state['action_url'] }}"
                        data-analytics-id="{{ $state['analytics_id'] }}"
                        data-analytics-type="permission_cta"
                    >{{ $state['action_label'] }}</a>
                @endif
            </section>
        </main>
            <x-public-footer />
    </body>
</html>
