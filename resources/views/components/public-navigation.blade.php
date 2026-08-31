@props([
    'active' => null,
    'mobile' => false,
    'extraClass' => '',
])

<nav class="{{ trim(($mobile ? 'mobile-bottom-nav' : 'tabs').' '.$extraClass) }}" aria-label="{{ $mobile ? 'Mobile menu' : 'Main menu' }}">
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
</nav>
