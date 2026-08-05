@php
    $avatarUrl = $user->avatar_path ? asset($user->avatar_path) : null;
    $selectedLocale = old('locale', $user->locale ?: 'en');
    $selectedCurrency = old('preferred_currency', strtoupper($user->preferred_currency ?: 'USD'));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Account | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="golden-stage-page account-page" data-analytics-screen="account" data-preferred-currency="{{ $selectedCurrency }}">
        <div class="store-shell home-shell golden-stage-shell account-shell account-stage-shell">
            @include('partials.stage-lights')

            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                        <img class="brand-logo" src="{{ asset('images/reny-renteria-logo-white.png') }}" alt="Reny Renteria">
                    </a>

                    <x-public-navigation />
                </div>

                <x-member-card />
            </aside>

            <main class="main-content store-content account-content golden-stage-main" id="account">
                <header class="mobile-header golden-stage-mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                            <img class="brand-logo" src="{{ asset('images/reny-renteria-logo-white.png') }}" alt="Reny Renteria">
                        </a>
                    </div>
                </header>

                <div class="account-stack">
                    @if (session('login_success'))
                        <div class="auth-success-box" role="status" aria-live="polite">{{ session('login_success') }}</div>
                    @endif

                    @if (session('account_profile_status') || session('account_preferences_status') || session('account_billing_status'))
                        <div class="account-status" role="status" aria-live="polite">
                            {{ session('account_profile_status') ?? session('account_preferences_status') ?? session('account_billing_status') }}
                        </div>
                    @endif

                    <section class="account-section account-profile-section" aria-labelledby="account-profile-title">
                        <div class="account-section-head">
                            <h1 id="account-profile-title">Profile</h1>
                        </div>

                        <div class="account-profile-grid">
                            <form
                                class="account-avatar-form"
                                method="POST"
                                action="{{ route('account.avatar.update') }}"
                                enctype="multipart/form-data"
                                data-account-avatar-form
                            >
                                @csrf
                                <label class="account-avatar-button" for="accountAvatarInput">
                                    @if ($avatarUrl)
                                        <img src="{{ $avatarUrl }}" alt="" data-account-avatar-preview>
                                    @else
                                        <span data-account-avatar-preview>{{ $initials ?: 'RR' }}</span>
                                    @endif
                                    <input id="accountAvatarInput" name="avatar" type="file" accept="image/png,image/jpeg,image/webp" data-account-avatar-input>
                                </label>
                                <p class="account-avatar-status" data-account-avatar-status>Click image to upload</p>
                                @error('avatar')
                                    <p class="account-field-error">{{ $message }}</p>
                                @enderror
                            </form>

                            <form class="account-form" method="POST" action="{{ route('account.profile.update') }}" data-account-dirty-form>
                                @csrf
                                @method('PATCH')
                                <label class="account-field" for="accountDisplayName">
                                    <span>Display Name</span>
                                    <input
                                        id="accountDisplayName"
                                        class="store-input"
                                        name="name"
                                        type="text"
                                        value="{{ old('name', $user->name) }}"
                                        autocomplete="name"
                                        required
                                    >
                                </label>
                                @error('name')
                                    <p class="account-field-error">{{ $message }}</p>
                                @enderror
                                <button class="store-button account-save-button" type="submit" data-account-dirty-submit @if (! $errors->has('name')) hidden @endif>Save Changes</button>
                            </form>
                        </div>
                    </section>

                    <section class="account-section account-upcoming-section" aria-labelledby="account-events-title">
                        <div class="account-section-head">
                            <h2 id="account-events-title">Upcoming Events</h2>
                        </div>

                        <div class="account-event-row">
                            <div class="account-event-group">
                                <div class="account-event-group-head">
                                    <span>Registered / Purchased</span>
                                </div>

                                <div class="account-event-list">
                                    @forelse ($registeredEvents as $eventCard)
                                        @include('account.partials.event-card', ['eventCard' => $eventCard])
                                    @empty
                                        <div class="account-empty">
                                            <strong>No upcoming events</strong>
                                            <span>Your tickets and RSVPs will appear here.</span>
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="account-event-group">
                                <div class="account-event-group-head">
                                    <span>Available Upcoming</span>
                                </div>

                                <div class="account-event-list">
                                    @forelse ($availableEvents as $eventCard)
                                        @include('account.partials.event-card', ['eventCard' => $eventCard])
                                    @empty
                                        <div class="account-empty">
                                            <strong>No new events</strong>
                                            <span>Discovery events will show here when available.</span>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="account-section account-points-section" aria-labelledby="account-points-title">
                        <div class="account-section-head">
                            <h2 id="account-points-title">Points</h2>
                        </div>
                        <div class="account-points-total">
                            <strong>{{ number_format($pointBalance) }} pts</strong>
                            <span>Total accumulated points</span>
                        </div>
                    </section>

                    <section class="account-section" aria-labelledby="account-purchases-title">
                        <div class="account-section-head">
                            <h2 id="account-purchases-title">Purchases</h2>
                        </div>

                        <div class="account-row-list">
                            @forelse ($purchases as $purchase)
                                <div class="account-row">
                                    <div>
                                        <strong>{{ $purchase['name'] }}</strong>
                                        <span>{{ $purchase['date'] }}</span>
                                    </div>
                                    <small>{{ $purchase['status'] }}</small>
                                </div>
                            @empty
                                <div class="account-empty">
                                    <strong>No purchases yet</strong>
                                    <span>Products you buy will appear here.</span>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    <section class="account-section" aria-labelledby="account-billing-title">
                        <div class="account-section-head">
                            <h2 id="account-billing-title">Billing</h2>
                        </div>

                        <dl class="account-billing-grid">
                            <div>
                                <dt>Next payment date</dt>
                                <dd>{{ $billingSummary['next_payment_date'] ?? 'Not scheduled' }}</dd>
                            </div>
                            <div>
                                <dt>Next charge</dt>
                                <dd>{{ $billingSummary['amount'] }}</dd>
                            </div>
                        </dl>

                        @if ($billingSummary['action'] === 'pause')
                            <button class="account-ghost-button" type="button" data-account-modal-open="pauseSubscriptionModal">Pause subscription</button>
                        @elseif ($billingSummary['action'] === 'reactivate')
                            <a class="account-ghost-button" href="{{ $billingSummary['reactivate_url'] }}">Reactivate subscription</a>
                        @endif
                    </section>

                    <section class="account-section" aria-labelledby="account-settings-title">
                        <div class="account-section-head">
                            <h2 id="account-settings-title">Settings</h2>
                        </div>

                        <form class="account-form account-preferences-form" method="POST" action="{{ route('account.preferences.update') }}" data-account-dirty-form>
                            @csrf
                            @method('PATCH')
                            <label class="account-field" for="accountLocale">
                                <span>Language preference</span>
                                <select id="accountLocale" class="store-input" name="locale">
                                    @foreach ($languages as $value => $label)
                                        <option value="{{ $value }}" @selected($selectedLocale === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="account-field" for="accountCurrency">
                                <span>Currency preference</span>
                                <select id="accountCurrency" class="store-input" name="preferred_currency">
                                    @foreach ($currencies as $value => $currency)
                                        <option value="{{ $value }}" @selected($selectedCurrency === $value)>{{ $value }} - {{ $currency['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>

                            @if ($errors->has('locale') || $errors->has('preferred_currency'))
                                <p class="account-field-error">{{ $errors->first('locale') ?: $errors->first('preferred_currency') }}</p>
                            @endif

                            <div class="account-settings-actions">
                                <button class="account-ghost-button" type="button" data-account-modal-open="paymentMethodModal">Change payment method</button>
                                <button class="store-button account-save-button" type="submit" data-account-dirty-submit @if (! $errors->has('locale') && ! $errors->has('preferred_currency')) hidden @endif>Save Preferences</button>
                            </div>
                        </form>

                        <form class="account-logout-form" method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="account-ghost-button account-logout-button" type="submit">Log out</button>
                        </form>
                    </section>
                </div>

                <x-public-navigation mobile />
            </main>
        </div>

        <section class="account-modal-layer" id="pauseSubscriptionModal" hidden inert>
            <div class="account-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="pauseSubscriptionTitle">
                <div class="store-dialog-head">
                    <h2 id="pauseSubscriptionTitle">Pause subscription</h2>
                    <button class="store-icon-button" type="button" data-account-modal-close="pauseSubscriptionModal" aria-label="Close pause subscription modal">Close</button>
                </div>
                <div class="account-modal-body">
                    <p>Your Royal Pass access stays active until the current paid period ends. Future PayPal renewals will be paused when a PayPal subscription ID is connected.</p>
                    <form method="POST" action="{{ route('account.subscription.pause') }}">
                        @csrf
                        <button class="store-button" type="submit">Confirm Pause</button>
                    </form>
                </div>
            </div>
        </section>

        <section class="account-modal-layer" id="paymentMethodModal" hidden inert>
            <div class="account-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="paymentMethodTitle">
                <div class="store-dialog-head">
                    <h2 id="paymentMethodTitle">Payment method</h2>
                    <button class="store-icon-button" type="button" data-account-modal-close="paymentMethodModal" aria-label="Close payment method modal">Close</button>
                </div>
                <div class="account-modal-body">
                    <p>PayPal manages payment methods for subscriptions from Automatic Payments. Use PayPal to update the funding source tied to this subscription.</p>
                    <a class="store-button" href="{{ $billingSummary['paypal_manage_url'] }}" target="_blank" rel="noreferrer">Open PayPal</a>
                </div>
            </div>
        </section>
    </body>
</html>
