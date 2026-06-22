@php
    $accountState = \App\Support\AccountStateView::for($user);
    $accountTimezone = $user->timezone ?: config('app.timezone');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Account | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="account" data-access-state="{{ $accountState['state'] }}">
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
                        <a class="tab" href="{{ url('/videos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="m22 8-6 4 6 4V8Z"></path>
                                <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                            </svg>
                            <span>VIDEOS</span>
                        </a>
                        <a class="tab" href="{{ url('/photos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <path d="m21 15-5-5L5 21"></path>
                            </svg>
                            <span>PHOTOS</span>
                        </a>
                        <a class="tab" href="{{ url('/community') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                            </svg>
                            <span>COMMUNITY</span>
                        </a>
                        <a class="tab" href="{{ url('/store') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M4 10h16"></path>
                                <path d="M5 10l1.5-5h11L19 10"></path>
                                <path d="M6 10v9h12v-9"></path>
                                <path d="M9 19v-5h6v5"></path>
                            </svg>
                            <span>STORE</span>
                        </a>
                        <a class="tab is-active" href="{{ route('account.show') }}" aria-current="page">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <circle cx="12" cy="8" r="4"></circle>
                                <path d="M4 21a8 8 0 0 1 16 0"></path>
                            </svg>
                            <span>ACCOUNT</span>
                        </a>
                    </nav>
                </div>

                <x-member-card />
            </aside>

            <main class="main-content account-content" id="account">
                @if (session('login_success'))
                    <div class="auth-success-box" role="status" aria-live="polite">{{ session('login_success') }}</div>
                @endif

                <header class="mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                            <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                        </a>
                    </div>
                </header>

                <section class="account-hero" aria-labelledby="account-title">
                    <div class="account-profile">
                        <div class="account-avatar" aria-hidden="true">
                            @if ($user->avatar_path)
                                <img src="{{ asset($user->avatar_path) }}" alt="">
                            @else
                                <span>{{ $initials ?: 'RR' }}</span>
                            @endif
                        </div>
                        <div>
                            <p class="account-kicker">User hub</p>
                            <h1 id="account-title">{{ $user->name }}</h1>
                            <p class="account-username">{{ $user->username ? '@'.$user->username : 'Username pending' }}</p>
                        </div>
                    </div>

                    <div class="account-membership">
                        <span class="account-badge {{ $accountState['badge_class'] }}">{{ $accountState['badge'] }}</span>
                        <p>{{ $accountState['description'] }}</p>

                        <dl class="account-state-details">
                            <div>
                                <dt>Account state</dt>
                                <dd>{{ $accountState['status_label'] }}</dd>
                            </div>
                            <div>
                                <dt>Billing status</dt>
                                <dd>{{ $billingProfile?->status ? str_replace('_', ' ', $billingProfile->status) : 'None' }}</dd>
                            </div>
                            <div>
                                <dt>Pass date</dt>
                                <dd>{{ $user->royal_ends_at?->timezone($accountTimezone)->format('M j, Y') ?? 'Not active' }}</dd>
                            </div>
                        </dl>

                        @if ($accountState['action_label'] && $accountState['action_url'])
                            <a
                                class="account-action"
                                href="{{ $accountState['action_url'] }}"
                                data-analytics-id="{{ $accountState['analytics_id'] }}"
                                data-analytics-type="account_state_cta"
                            >{{ $accountState['action_label'] }}</a>
                        @endif
                    </div>
                </section>

                <section class="account-grid" aria-label="Account dashboard">
                    <article class="account-section account-section-wide">
                        <div class="account-section-head">
                            <h2>Upcoming Events</h2>
                            <span>{{ $upcomingTickets->count() }} active</span>
                        </div>

                        @forelse ($upcomingTickets as $ticket)
                            <div class="account-row">
                                <div>
                                    <strong>{{ $ticket->event->title }}</strong>
                                    <span>{{ $ticket->event->starts_at->timezone($ticket->event->timezone)->format('M j, Y g:i A') }} · {{ $ticket->event->venue ?: 'Venue pending' }}</span>
                                    <small>{{ $ticket->event->address ?: 'Address pending' }}</small>
                                </div>
                                <div class="account-row-meta">
                                    <span>{{ str_replace('_', ' ', $ticket->status) }}</span>
                                    <code>{{ $ticketDisplayCodes[$ticket->id] ?? $ticket->ticket_code_preview ?? 'QR' }}</code>
                                </div>
                            </div>
                        @empty
                            <div class="account-empty">
                                <strong>No upcoming events</strong>
                                <span>Your tickets and RSVP status will appear here.</span>
                            </div>
                        @endforelse
                    </article>

                    <article class="account-section">
                        <div class="account-section-head">
                            <h2>Library</h2>
                            <span>{{ $unlocks->count() }} unlocked</span>
                        </div>

                        @forelse ($unlocks as $unlock)
                            <div class="account-row account-row-compact">
                                <div>
                                    <strong>{{ $unlock->title }}</strong>
                                    <span>{{ ucfirst($unlock->unlock_type) }} · {{ ucfirst($unlock->status) }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="account-empty">
                                <strong>No purchases yet</strong>
                                <span>Albums, videos and drops you buy will stay available here.</span>
                            </div>
                        @endforelse
                    </article>

                    <article class="account-section">
                        <div class="account-section-head">
                            <h2>Billing</h2>
                            <span>{{ $billingProfile?->provider ? strtoupper($billingProfile->provider) : 'None' }}</span>
                        </div>

                        @if ($billingProfile)
                            <dl class="account-facts">
                                <div>
                                    <dt>Status</dt>
                                    <dd>{{ str_replace('_', ' ', $billingProfile->status) }}</dd>
                                </div>
                                <div>
                                    <dt>Method</dt>
                                    <dd>{{ $billingProfile->payment_method_summary ?: 'PayPal' }}</dd>
                                </div>
                                <div>
                                    <dt>Renews</dt>
                                    <dd>{{ $billingProfile->current_period_ends_at?->timezone($user->timezone)->format('M j, Y') ?? 'Not scheduled' }}</dd>
                                </div>
                            </dl>
                        @else
                            <div class="account-empty">
                                <strong>No billing profile</strong>
                                <span>PayPal-backed billing details will appear after checkout.</span>
                            </div>
                        @endif
                    </article>

                    <article class="account-section">
                        <div class="account-section-head">
                            <h2>Points</h2>
                            <span>{{ number_format($pointBalance) }}</span>
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
                                <span>Activity points will count toward the leaderboard.</span>
                            </div>
                        @endif
                    </article>

                    <article class="account-section">
                        <div class="account-section-head">
                            <h2>Purchases</h2>
                            <span>{{ $recentOrders->count() }} recent</span>
                        </div>

                        @forelse ($recentOrders as $order)
                            <div class="account-row account-row-compact">
                                <div>
                                    <strong>{{ ucfirst($order->product_key) }}</strong>
                                    <span>{{ strtoupper($order->currency) }} {{ number_format($order->amount_cents / 100, 2) }} · {{ ucfirst($order->status) }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="account-empty">
                                <strong>No recent purchases</strong>
                                <span>Completed orders will appear here after PayPal confirmation.</span>
                            </div>
                        @endforelse
                    </article>

                    <article class="account-section">
                        <div class="account-section-head">
                            <h2>Settings</h2>
                            <span>{{ strtoupper($user->preferred_currency ?? 'USD') }}</span>
                        </div>

                        <dl class="account-facts">
                            <div>
                                <dt>Country</dt>
                                <dd>{{ $user->country_code ?: 'Pending' }}</dd>
                            </div>
                            <div>
                                <dt>Language</dt>
                                <dd>{{ $user->locale ?: 'en' }}</dd>
                            </div>
                            <div>
                                <dt>Timezone</dt>
                                <dd>{{ $user->timezone ?: 'America/Panama' }}</dd>
                            </div>
                            <div>
                                <dt>Data requests</dt>
                                <dd>Manual request</dd>
                            </div>
                        </dl>
                    </article>
                </section>

                <form method="POST" action="{{ route('logout') }}" class="account-logout">
                    @csrf
                    <button class="auth-secondary-button" type="submit">Log out</button>
                </form>
            </main>
        </div>
    </body>
</html>
