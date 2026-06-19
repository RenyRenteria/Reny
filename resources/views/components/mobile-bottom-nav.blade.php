@props(['active' => null])

@php
    $user = auth()->user();
    $state = \App\Support\AccountStateView::for($user);
    $isRoyal = in_array($state['state'], ['royal_active', 'royal_grace'], true);
    $authLabel = $user ? ($isRoyal ? 'ROYAL' : 'ACCOUNT') : 'SIGN IN';
    $authHref = $user ? route('account.show') : route('login');
    $authEvent = $user ? 'account_navigation_clicked' : 'auth_login_started';
    $authScreenReaderLabel = $user
        ? 'Account, '.$state['badge']
        : 'Sign in, Guest';
@endphp

<nav class="mobile-bottom-nav" aria-label="Mobile menu">
    <a class="{{ $active === 'music' ? 'is-active' : '' }}" href="{{ $active === 'music' ? '#music' : route('home') }}" @if ($active === 'music') data-tab-link="music" aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M9 18V5l10-2v13"></path>
            <circle cx="7" cy="18" r="3"></circle>
            <circle cx="17" cy="16" r="3"></circle>
        </svg>
        <span class="sr-only">MUSIC</span>
    </a>
    <a class="{{ $active === 'videos' ? 'is-active' : '' }}" href="{{ url('/videos') }}" @if ($active === 'videos') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="m22 8-6 4 6 4V8Z"></path>
            <rect x="2" y="6" width="14" height="12" rx="2"></rect>
        </svg>
        <span class="sr-only">VIDEOS</span>
    </a>
    <a class="{{ $active === 'photos' ? 'is-active' : '' }}" href="{{ url('/photos') }}" @if ($active === 'photos') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
            <circle cx="8.5" cy="8.5" r="1.5"></circle>
            <path d="m21 15-5-5L5 21"></path>
        </svg>
        <span class="sr-only">PHOTOS</span>
    </a>
    <a class="{{ $active === 'community' ? 'is-active' : '' }}" href="{{ url('/community') }}" @if ($active === 'community') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
        </svg>
        <span class="sr-only">COMMUNITY</span>
    </a>
    <a class="{{ $active === 'store' ? 'is-active' : '' }}" href="{{ url('/store') }}" @if ($active === 'store') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M4 10h16"></path>
            <path d="M5 10l1.5-5h11L19 10"></path>
            <path d="M6 10v9h12v-9"></path>
            <path d="M9 19v-5h6v5"></path>
        </svg>
        <span class="sr-only">STORE</span>
    </a>
    <a
        class="mobile-auth-link {{ $active === 'account' ? 'is-active' : '' }}"
        href="{{ $authHref }}"
        data-access-state="{{ $state['state'] }}"
        data-analytics-event="{{ $authEvent }}"
        data-analytics-id="mobile_auth_{{ $state['state'] }}"
        data-analytics-label="{{ $authScreenReaderLabel }}"
        data-analytics-surface="mobile_bottom_nav"
        data-analytics-type="account_link"
        @if ($active === 'account') aria-current="page" @endif
    >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            @if ($user)
                <circle cx="12" cy="8" r="4"></circle>
                <path d="M4 21a8 8 0 0 1 16 0"></path>
            @else
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                <path d="m10 17 5-5-5-5"></path>
                <path d="M15 12H3"></path>
            @endif
        </svg>
        <span class="sr-only">{{ $authScreenReaderLabel }}</span>
        <span class="mobile-auth-status" aria-hidden="true">{{ $authLabel }}</span>
    </a>
</nav>
