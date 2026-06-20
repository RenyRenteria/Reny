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

                    <nav class="tabs" aria-label="Main menu">
                        <a class="tab" href="{{ route('music') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M9 18V5l10-2v13"></path>
                                <circle cx="7" cy="18" r="3"></circle>
                                <circle cx="17" cy="16" r="3"></circle>
                            </svg>
                            <span>MUSIC</span>
                        </a>
                        <a class="tab" href="{{ route('account.show') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <circle cx="12" cy="8" r="4"></circle>
                                <path d="M4 21a8 8 0 0 1 16 0"></path>
                            </svg>
                            <span>ACCOUNT</span>
                        </a>
                        <a class="tab is-active" href="{{ route('points.index') }}" aria-current="page">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M8 21h8"></path>
                                <path d="M12 17v4"></path>
                                <path d="M7 4h10v4a5 5 0 0 1-10 0V4Z"></path>
                                <path d="M5 5H3v2a4 4 0 0 0 4 4"></path>
                                <path d="M19 5h2v2a4 4 0 0 1-4 4"></path>
                            </svg>
                            <span>POINTS</span>
                        </a>
                    </nav>
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
                        <div class="account-avatar" aria-hidden="true">
                            <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
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
