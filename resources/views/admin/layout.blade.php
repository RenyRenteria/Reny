@php
    $adminSection = trim($__env->yieldContent('admin_section', 'dashboard'));
    $adminTheme = trim($__env->yieldContent('admin_theme', 'neutral'));
    $user = auth()->user();
    $roleLabel = $user ? str_replace('_', ' ', $user->role) : 'admin';
    $sectionRoute = fn (string $section): string => $section === 'dashboard'
        ? route('admin.dashboard')
        : route('admin.dashboard').'#'.$section;
    $isActive = fn (string $section): bool => $adminSection === $section;
    $navClass = fn (string $section): string => $isActive($section) ? 'tab sidebar-btn is-active' : 'tab sidebar-btn';
    $publicTabClass = fn (array|string $sections): string => in_array($adminSection, (array) $sections, true)
        ? 'ds-main-tab is-selected'
        : 'ds-main-tab';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @yield('head')

        <title>@yield('title', 'Admin CMS') | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="admin-cms-body" data-theme="{{ $adminTheme }}" data-admin-current-section="{{ $adminSection }}">
        <div
            id="toastNotification"
            class="admin-toast"
            role="status"
            aria-live="polite"
            aria-hidden="true"
        >
            <div id="toastIcon" class="admin-toast-icon" aria-hidden="true"></div>
            <div class="admin-toast-copy">
                <p id="toastTitle"></p>
                <span id="toastMessage"></span>
            </div>
            <button type="button" class="admin-toast-close" data-admin-close-toast aria-label="Cerrar notificacion">×</button>
        </div>

        <div class="admin-public-shell music-shell">
            <aside id="sidebar" class="sidebar admin-cms-sidebar" aria-label="Admin navigation">
                <div>
                    <a class="brand-link" href="{{ route('admin.dashboard') }}" aria-label="Reny Renteria CMS">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <nav class="tabs admin-side-nav" aria-label="CMS sections">
                        <a class="{{ $navClass('banners') }}" href="{{ $sectionRoute('banners') }}" data-admin-nav="banners">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                                <path d="M7 9h10"></path>
                                <path d="M7 13h6"></path>
                            </svg>
                            <span>Banners</span>
                        </a>
                        <a class="{{ $navClass('contenido') }}" href="{{ route('admin.content.index', ['section' => 'music']) }}" data-admin-nav="contenido">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M9 18V5l10-2v13"></path>
                                <circle cx="7" cy="18" r="3"></circle>
                                <circle cx="17" cy="16" r="3"></circle>
                            </svg>
                            <span>Music</span>
                        </a>
                        <a class="{{ $navClass('biblioteca') }}" href="{{ route('admin.content.index', ['section' => 'video']) }}" data-admin-nav="biblioteca">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="m22 8-6 4 6 4V8Z"></path>
                                <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                            </svg>
                            <span>Videos</span>
                        </a>
                        <a class="{{ $navClass('photos') }}" href="{{ route('admin.content.index', ['section' => 'photos']) }}" data-admin-nav="photos">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <path d="m21 15-5-5L5 21"></path>
                            </svg>
                            <span>Photos</span>
                        </a>
                        <a class="{{ $navClass('comunidad') }}" href="{{ route('admin.content.index', ['section' => 'community']) }}" data-admin-nav="comunidad">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                            </svg>
                            <span>Community</span>
                        </a>
                        <a class="{{ $navClass('productos') }}" href="{{ route('admin.content.index', ['section' => 'store']) }}" data-admin-nav="productos">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M4 10h16"></path>
                                <path d="M5 10l1.5-5h11L19 10"></path>
                                <path d="M6 10v9h12v-9"></path>
                                <path d="M9 19v-5h6v5"></path>
                            </svg>
                            <span>Store</span>
                        </a>

                        <p>CMS</p>

                        <a class="{{ $navClass('dashboard') }}" href="{{ route('admin.dashboard') }}" data-admin-nav="dashboard">
                            <span class="admin-nav-icon" aria-hidden="true">RR</span>
                            <span>Resumen</span>
                        </a>
                        <a class="{{ $navClass('site-editor') }}" href="{{ route('admin.site-editor.index') }}" data-admin-nav="site-editor">
                            <span class="admin-nav-icon" aria-hidden="true">▣</span>
                            <span>Site Editor</span>
                        </a>
                        <a class="{{ $navClass('editor') }}" href="{{ route('admin.content.create') }}" data-admin-nav="editor">
                            <span class="admin-nav-icon" aria-hidden="true">+</span>
                            <span>Nuevo Content</span>
                        </a>
                        <a class="{{ $navClass('media') }}" href="{{ route('admin.media.index') }}" data-admin-nav="media">
                            <span class="admin-nav-icon" aria-hidden="true">▧</span>
                            <span>Media Library</span>
                        </a>
                        <a class="{{ $navClass('royalpass') }}" href="{{ $sectionRoute('royalpass') }}" data-admin-nav="royalpass">
                            <span class="admin-nav-icon" aria-hidden="true">♛</span>
                            <span>Royal Pass <small>PRO</small></span>
                        </a>
                        <a class="{{ $navClass('usuarios') }}" href="{{ $sectionRoute('usuarios') }}" data-admin-nav="usuarios">
                            <span class="admin-nav-icon" aria-hidden="true">◎</span>
                            <span>Usuarios</span>
                        </a>
                        <a class="{{ $navClass('eventos') }}" href="{{ $sectionRoute('eventos') }}" data-admin-nav="eventos">
                            <span class="admin-nav-icon" aria-hidden="true">◴</span>
                            <span>Events / Tickets</span>
                        </a>
                        <a class="{{ $navClass('puntos') }}" href="{{ $sectionRoute('puntos') }}" data-admin-nav="puntos">
                            <span class="admin-nav-icon" aria-hidden="true">◇</span>
                            <span>Puntos</span>
                        </a>
                        <a class="{{ $navClass('pagos') }}" href="{{ $sectionRoute('pagos') }}" data-admin-nav="pagos">
                            <span class="admin-nav-icon" aria-hidden="true">$</span>
                            <span>Pagos</span>
                        </a>
                        <a class="{{ $navClass('notificaciones') }}" href="{{ $sectionRoute('notificaciones') }}" data-admin-nav="notificaciones">
                            <span class="admin-nav-icon" aria-hidden="true">✉</span>
                            <span>Anuncios</span>
                        </a>
                        <a class="{{ $navClass('equipo') }}" href="{{ $sectionRoute('equipo') }}" data-admin-nav="equipo">
                            <span class="admin-nav-icon" aria-hidden="true">◌</span>
                            <span>Equipo</span>
                        </a>
                        <a class="{{ $navClass('historial') }}" href="{{ $sectionRoute('historial') }}" data-admin-nav="historial">
                            <span class="admin-nav-icon" aria-hidden="true">◷</span>
                            <span>Historial</span>
                        </a>
                        <a class="{{ $navClass('ajustes') }}" href="{{ $sectionRoute('ajustes') }}" data-admin-nav="ajustes">
                            <span class="admin-nav-icon" aria-hidden="true">⚙</span>
                            <span>Ajustes</span>
                        </a>
                    </nav>
                </div>

                <div class="member-card admin-member-card" data-access-state="royal_active">
                    <div class="member-avatar" aria-hidden="true"></div>
                    <div>
                        <strong>{{ $user?->name ?? 'Admin' }}</strong>
                        <span>{{ $roleLabel }}</span>
                        <a class="member-card-link" href="{{ route('home') }}" target="_blank" rel="noreferrer">Ver sitio</a>
                    </div>
                </div>

                @if ($user)
                    <form class="admin-logout-form" method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="admin-button admin-button-secondary" type="submit">Log out</button>
                    </form>
                @endif
            </aside>

            <button id="sidebarOverlay" type="button" class="admin-sidebar-overlay" data-admin-sidebar-toggle aria-label="Cerrar menu"></button>

            <main class="main-content admin-cms-main">
                <header class="mobile-header admin-mobile-header">
                    <button type="button" class="admin-icon-button admin-mobile-menu" data-admin-sidebar-toggle aria-label="Abrir menu">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>

                    <a class="brand-link" href="{{ route('admin.dashboard') }}" aria-label="Reny Renteria CMS">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>
                </header>

                <nav class="ds-public-tabs" aria-label="Tabs publicos del producto">
                    <a class="{{ $publicTabClass('banners') }}" href="{{ $sectionRoute('banners') }}" data-admin-nav="banners" style="--tab-accent: var(--red); --tab-soft: var(--rose);">
                        <span>Banners</span>
                        <span class="ds-tab-dot" aria-hidden="true"></span>
                    </a>
                    <a class="{{ $publicTabClass('contenido') }}" href="{{ route('admin.content.index', ['section' => 'music']) }}" data-admin-nav="contenido" style="--tab-accent: var(--red); --tab-soft: var(--rose);">
                        <span>Music</span>
                        <span class="ds-tab-dot" aria-hidden="true"></span>
                    </a>
                    <a class="{{ $publicTabClass('biblioteca') }}" href="{{ route('admin.content.index', ['section' => 'video']) }}" data-admin-nav="biblioteca" style="--tab-accent: var(--purple); --tab-soft: #eee8ff;">
                        <span>Videos</span>
                        <span class="ds-tab-dot" aria-hidden="true"></span>
                    </a>
                    <a class="{{ $publicTabClass('photos') }}" href="{{ route('admin.content.index', ['section' => 'photos']) }}" data-admin-nav="photos" style="--tab-accent: var(--accent); --tab-soft: var(--accent-soft);">
                        <span>Photos</span>
                        <span class="ds-tab-dot" aria-hidden="true"></span>
                    </a>
                    <a class="{{ $publicTabClass('comunidad') }}" href="{{ route('admin.content.index', ['section' => 'community']) }}" data-admin-nav="comunidad" style="--tab-accent: var(--accent-dark); --tab-soft: var(--accent-soft);">
                        <span>Community</span>
                        <span class="ds-tab-dot" aria-hidden="true"></span>
                    </a>
                    <a class="{{ $publicTabClass('productos') }}" href="{{ route('admin.content.index', ['section' => 'store']) }}" data-admin-nav="productos" style="--tab-accent: var(--red); --tab-soft: var(--rose);">
                        <span>Store</span>
                        <span class="ds-tab-dot" aria-hidden="true"></span>
                    </a>
                </nav>

                @if (session('status'))
                    <div class="auth-status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="auth-status" role="alert">{{ $errors->first() }}</div>
                @endif

                @yield('content')
            </main>
        </div>

        @yield('scripts')
    </body>
</html>
