@php
    $sourceRoute = $stateView['source_route'] ?? '';
    $reactivationActions = ['upgrade', 'reactivate', 'update_payment', 'repurchase'];
    $primaryClasses = 'auth-button permission-gate-action access-gate-button';

    if (in_array($stateView['primary_action'], $reactivationActions, true)) {
        $primaryClasses .= ' reactivation-action';
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $stateView['paywall_title'] }} | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="permission_denied" data-access-state="{{ $stateView['state'] }}" data-source-route="{{ $sourceRoute }}">
        <main class="auth-shell">
            <section
                class="auth-panel permission-gate"
                aria-labelledby="permission-title"
                data-section="{{ $section ?? 'royal' }}"
                data-access-state="{{ $stateView['state'] }}"
            >
                <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>

                <p class="auth-kicker">{{ $stateView['label'] }}</p>
                <h1 id="permission-title">{{ $stateView['paywall_title'] }}</h1>
                <p class="auth-copy">{{ $stateView['paywall_copy'] }}</p>

                <div class="permission-actions">
                    <a
                        class="{{ $primaryClasses }}"
                        href="{{ $stateView['primary_url'] }}"
                        data-reactivation-action="{{ $stateView['primary_action'] }}"
                        data-access-state="{{ $stateView['state'] }}"
                    >{{ $stateView['primary_label'] }}</a>

                    @if ($stateView['secondary_label'] && $stateView['secondary_url'])
                        <a class="auth-secondary-button" href="{{ $stateView['secondary_url'] }}">{{ $stateView['secondary_label'] }}</a>
                    @endif
                </div>
            </section>
        </main>
    </body>
</html>
