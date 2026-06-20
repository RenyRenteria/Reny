@php
    $showSidebarToggle = $showSidebarToggle ?? false;
    $currentAdminSection = $adminSection ?? trim($__env->yieldContent('admin_section', ''));
    $currentPage = request()->route('page');
    $contentSection = request()->query('section');
    $isContentRoute = request()->routeIs('admin.content.*') || request()->routeIs('admin.editorial.*');
    $isSiteEditorRoute = request()->routeIs('admin.site-editor.*');

    $headerTabs = [
        [
            'label' => 'HOME',
            'href' => route('admin.site-editor.show', ['page' => 'home']),
            'page' => 'home',
            'content_section' => 'home',
            'nav' => 'site-editor',
        ],
        [
            'label' => 'MUSIC',
            'href' => route('admin.site-editor.show', ['page' => 'music']),
            'page' => 'music',
            'content_section' => 'music',
            'nav' => 'site-editor',
        ],
        [
            'label' => 'VIDEOS',
            'href' => route('admin.site-editor.show', ['page' => 'videos']),
            'page' => 'videos',
            'content_section' => 'video',
            'nav' => 'site-editor',
        ],
        [
            'label' => 'PHOTOS',
            'href' => route('admin.site-editor.show', ['page' => 'photos']),
            'page' => 'photos',
            'content_section' => 'video',
            'nav' => 'site-editor',
        ],
        [
            'label' => 'COMMUNITY',
            'href' => route('admin.site-editor.show', ['page' => 'community']),
            'page' => 'community',
            'content_section' => 'community',
            'nav' => 'site-editor',
        ],
        [
            'label' => 'STORE',
            'href' => route('admin.site-editor.show', ['page' => 'store']),
            'page' => 'store',
            'content_section' => 'events',
            'nav' => 'site-editor',
        ],
        [
            'label' => 'STATS',
            'href' => route('admin.dashboard'),
            'section' => 'stats',
            'nav' => 'stats',
        ],
    ];

    $isActiveHeaderTab = function (array $tab) use ($contentSection, $currentAdminSection, $currentPage, $isContentRoute, $isSiteEditorRoute): bool {
        if (($tab['section'] ?? null) === $currentAdminSection) {
            return true;
        }

        if ($isSiteEditorRoute && isset($tab['page'])) {
            return $currentPage === $tab['page'];
        }

        if ($isContentRoute && isset($tab['content_section'])) {
            return $contentSection === $tab['content_section'];
        }

        return false;
    };
@endphp

<header class="admin-cms-header">
    <div class="admin-brand-wrap">
        @if ($showSidebarToggle)
            <button type="button" class="admin-icon-button admin-mobile-menu" data-admin-sidebar-toggle aria-label="Abrir menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        @endif

        <a class="admin-cms-brand" href="{{ route('admin.dashboard') }}" aria-label="Reny Renteria CMS">
            <img
                class="admin-cms-logo"
                src="{{ asset('images/reny-renteria-logo.png') }}"
                alt="Reny Renteria"
            >
        </a>
    </div>

    <nav class="admin-public-header-nav" aria-label="CMS primary sections">
        @foreach ($headerTabs as $tab)
            @php($isActive = $isActiveHeaderTab($tab))
            <a
                @class(['is-active' => $isActive])
                href="{{ $tab['href'] }}"
                data-admin-nav="{{ $tab['nav'] }}"
                @if ($isActive) aria-current="page" @endif
            >
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="admin-header-actions">
        <span class="admin-money-link" aria-hidden="true">
            <img
                class="admin-money-icon"
                src="{{ asset('images/admin-money-icon.png') }}"
                alt=""
                aria-hidden="true"
            >
        </span>

        @auth
            <form method="POST" action="{{ route('admin.logout') }}" class="admin-header-logout">
                @csrf
                <button class="admin-button admin-button-secondary" type="submit">Log out</button>
            </form>
        @endauth
    </div>
</header>
