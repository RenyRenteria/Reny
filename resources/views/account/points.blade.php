<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Points | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="account_points">
        <div class="music-shell">
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                        <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                    </a>

                    <x-public-navigation />
                </div>

                <x-member-card />
            </aside>

            <main class="main-content account-content" id="points">
                <header class="mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                            <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                        </a>
                    </div>
                </header>

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
        </div>
    </body>
</html>
