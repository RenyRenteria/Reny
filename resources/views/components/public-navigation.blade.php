@php
    $navigation = [
        ['label' => 'Shop', 'url' => route('store'), 'active' => request()->routeIs('store*', 'merch', 'yoycol.*')],
        ['label' => 'Shows', 'url' => route('shows'), 'active' => request()->routeIs('shows')],
        ['label' => 'Royal Pass', 'url' => route('royals'), 'active' => request()->routeIs('royals', 'community.*', 'royal.*')],
        ['label' => 'Music', 'url' => route('music'), 'active' => request()->routeIs('music*', 'videos', 'photos')],
        ['label' => 'My Account', 'url' => route('account.show'), 'active' => request()->routeIs('account.*', 'points.*', 'login', 'register', 'password.*')],
    ];
@endphp
<header class="site-header" id="top">
    <a class="site-skip" href="#site-content">Saltar al contenido</a>
    <a class="site-signature" href="{{ route('home') }}" aria-label="Reny Renteria, inicio" @if (request()->routeIs('home')) aria-current="page" @endif>
        <img src="{{ asset('newlanding-assets/assets/signature-black.png') }}" alt="Reny Renteria" width="170" height="64">
    </a>
    <nav class="site-desktop-nav" aria-label="Main menu">
        @foreach ($navigation as $item)
            <a href="{{ $item['url'] }}" @class(['site-nav-account' => $loop->last]) @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
        @endforeach
    </nav>
    <details class="site-mobile-nav" data-site-disclosure>
        <summary><span>Menú</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 7h18M3 17h18" /></svg></summary>
        <nav aria-label="Mobile menu">
            @foreach ($navigation as $item)
                <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}<span aria-hidden="true">↗</span></a>
            @endforeach
        </nav>
    </details>
</header>
