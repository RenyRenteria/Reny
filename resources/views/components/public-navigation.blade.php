@props([
    'active' => null,
    'mobile' => false,
    'extraClass' => '',
])

<nav class="{{ trim(($mobile ? 'mobile-bottom-nav' : 'tabs').' '.$extraClass) }}" aria-label="{{ $mobile ? 'Mobile menu' : 'Main menu' }}">
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'home']) href="{{ route('home') }}"@if ($active === 'home') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m3 10 9-7 9 7v11h-6v-7H9v7H3Z"/></svg>
        <span @class(['sr-only' => $mobile])>Home</span>
    </a>
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'videos']) href="{{ route('videos') }}"@if ($active === 'videos') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
        <span @class(['sr-only' => $mobile])>Videos</span>
    </a>
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'music']) href="{{ route('music') }}"@if ($active === 'music') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M9 18V5l10-2v13"/><circle cx="7" cy="18" r="3"/><circle cx="17" cy="16" r="3"/></svg>
        <span @class(['sr-only' => $mobile])>Música</span>
    </a>
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'merch']) href="{{ route('merch') }}"@if ($active === 'merch') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M5 7h14l2 14H3L5 7Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
        <span @class(['sr-only' => $mobile])>Merch</span>
    </a>
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'shows']) href="{{ route('shows') }}"@if ($active === 'shows') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M6 3v3M18 3v3M4 9h16"/><rect x="3" y="5" width="18" height="16" rx="2"/><path d="m9 14 2 2 4-4"/></svg>
        <span @class(['sr-only' => $mobile])>Shows</span>
    </a>
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'bio']) href="{{ route('bio') }}"@if ($active === 'bio') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>
        <span @class(['sr-only' => $mobile])>Bio</span>
    </a>
    <a @class(['tab' => ! $mobile, 'is-active' => $active === 'contacto']) href="{{ route('contacto') }}"@if ($active === 'contacto') aria-current="page"@endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        <span @class(['sr-only' => $mobile])>Contacto</span>
    </a>
</nav>
