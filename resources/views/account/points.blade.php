<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        @include('partials.google-analytics')
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Points | Reny Renteria</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="reny-site" data-analytics-screen="account_points">
        <div class="music-shell">
            <x-public-navigation />

            <main class="main-content account-content" id="points">
                <span id="site-content" class="sr-only" tabindex="-1"></span>

                <section class="account-hero" aria-labelledby="points-title">
                    <div class="account-profile">
                        <div class="account-avatar" aria-hidden="true" data-profile-avatar-display>
                            @if ($user->avatar_path)
                                <img src="{{ asset($user->avatar_path) }}" alt="">
                            @else
                                <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                            @endif
                        </div>
                        <div>
                            <p class="account-kicker">Leaderboard</p>
                            <h1 id="points-title">Points</h1>
                            <p class="account-username">{{ $user->username ? '@'.$user->username : $user->name }}</p>
                        </div>
                    </div>

                    <div class="account-membership">
                        <span class="account-badge">{{ number_format($pointBalance) }} points</span>
                        <p>V1 points count toward leaderboard only. Redemptions are outside V1.</p>
                    </div>
                </section>

                <section class="account-grid" aria-label="Points dashboard">
                    <article class="account-section account-section-wide">
                        <div class="account-section-head">
                            <h2>Leaderboard</h2>
                            <span>{{ $leaderboard->count() }} ranked</span>
                        </div>

                        @if ($leaderboard->isNotEmpty())
                            <ol class="account-leaderboard">
                                @foreach ($leaderboard as $entry)
                                    <li>
                                        <span>{{ $entry->user?->username ? '@'.$entry->user->username : $entry->user?->name }}</span>
                                        <strong>{{ number_format($entry->points) }}</strong>
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <div class="account-empty">
                                <strong>No points yet</strong>
                                <span>Validated activity will appear on the leaderboard.</span>
                            </div>
                        @endif
                    </article>
                </section>
            </main>
            <x-public-footer />
        </div>
    </body>
</html>
