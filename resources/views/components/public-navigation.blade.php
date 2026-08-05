@props([
    'active' => null,
    'mobile' => false,
    'extraClass' => '',
])

@php
    $viewer = request()->attributes->get('site_editor_guest_preview', false) ? null : auth()->user();
    $mobileAccountState = $mobile ? \App\Support\AccountStateView::for($viewer) : null;
    $mobileAccountLabel = $viewer ? 'Account' : 'Sign in';
    $mobileAccountStatus = ! $viewer
        ? 'Guest'
        : ($viewer->hasRoyalAccess() || $viewer->isStaff() ? 'Royal' : 'Logged in');
    $mobileAccountUrl = $viewer ? route('account.show') : route('login');
    $mobileAccountIsActive = request()->routeIs('account.*', 'login', 'register', 'password.*');
@endphp

<nav class="{{ trim(($mobile ? 'mobile-bottom-nav' : 'tabs').' '.$extraClass) }}" aria-label="{{ $mobile ? 'Mobile menu' : 'Main menu' }}">
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'royals']) href="{{ url('/community') }}"@if ($active === 'royals') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
        </svg>
        <span @class(['sr-only' => $mobile])>Royals</span>
    </a>
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'videos']) href="{{ route('videos') }}"@if ($active === 'videos') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="m22 8-6 4 6 4V8Z"></path>
            <rect x="2" y="6" width="14" height="12" rx="2"></rect>
        </svg>
        <span @class(['sr-only' => $mobile])>Videos</span>
    </a>
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'music']) href="{{ route('music') }}"@if ($active === 'music') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M9 18V5l10-2v13"></path>
            <circle cx="7" cy="18" r="3"></circle>
            <circle cx="17" cy="16" r="3"></circle>
        </svg>
        <span @class(['sr-only' => $mobile])>Music</span>
    </a>
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'shows']) href="{{ route('shows') }}"@if ($active === 'shows') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M6 3v3M18 3v3M4 9h16"></path>
            <rect x="3" y="5" width="18" height="16" rx="2"></rect>
            <path d="m9 14 2 2 4-4"></path>
        </svg>
        <span @class(['sr-only' => $mobile])>Shows</span>
    </a>
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'store']) href="{{ route('store') }}"@if ($active === 'store') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M4 10h16"></path>
            <path d="M5 10l1.5-5h11L19 10"></path>
            <path d="M6 10v9h12v-9"></path>
            <path d="M9 19v-5h6v5"></path>
        </svg>
        <span @class(['sr-only' => $mobile])>Store</span>
    </a>
    @if ($mobile)
        <a
            @class(['mobile-nav-account', 'account-action', 'is-active' => $mobileAccountIsActive])
            href="{{ $mobileAccountUrl }}"
            aria-label="{{ $mobileAccountLabel }} — {{ $mobileAccountStatus }}"
            data-access-state="{{ $mobileAccountState['state'] }}"
            data-analytics-id="mobile_{{ $viewer ? 'account' : 'sign_in' }}"
            data-analytics-type="mobile_navigation"
            @if ($mobileAccountIsActive) aria-current="page" @endif
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <circle cx="12" cy="8" r="4"></circle>
                <path d="M4.5 21a7.5 7.5 0 0 1 15 0"></path>
            </svg>
            <span class="mobile-nav-account-copy" aria-hidden="true">
                <span class="mobile-nav-account-label">{{ $mobileAccountLabel }}</span>
                <span class="mobile-nav-account-status">{{ $mobileAccountStatus }}</span>
            </span>
        </a>
    @endif
</nav>
