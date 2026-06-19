@php
    $adminSection = trim($__env->yieldContent('admin_section', 'dashboard'));
    $adminTheme = trim($__env->yieldContent('admin_theme', 'neutral'));
    $user = auth()->user();
    $roleLabel = $user ? str_replace('_', ' ', $user->role) : 'admin';
    $sectionRoute = fn (string $section): string => $section === 'dashboard'
        ? route('admin.dashboard')
        : route('admin.dashboard').'#'.$section;
    $isActive = fn (string $section): bool => $adminSection === $section;
    $navClass = fn (string $section): string => $isActive($section) ? 'sidebar-btn is-active' : 'sidebar-btn';
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
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

        <header class="admin-cms-header">
            <div class="admin-brand-wrap">
                <button type="button" class="admin-icon-button admin-mobile-menu" data-admin-sidebar-toggle aria-label="Abrir menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <a class="admin-cms-brand" href="{{ route('admin.dashboard') }}" aria-label="Reny Renteria CMS">
                    <span class="admin-brand-mark">RR</span>
                    <span>
                        <strong>RenyRenteria.com</strong>
                        <small>CMS editorial</small>
                    </span>
                </a>
            </div>

            <div class="admin-header-actions">
                <a class="admin-site-link" href="{{ route('home') }}" target="_blank" rel="noreferrer">
                    <span>Ver sitio web</span>
                    <span aria-hidden="true">↗</span>
                </a>

                @if ($user)
                    <div class="admin-user-pill">
                        <span class="admin-user-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <span>
                            <strong>{{ $user->name }}</strong>
                            <small>{{ $roleLabel }}</small>
                        </span>
                    </div>
                @endif
            </div>
        </header>

        <div class="admin-cms-frame">
            <aside id="sidebar" class="admin-cms-sidebar" aria-label="Admin navigation">
                <div class="admin-sidebar-scroll">
                    <nav class="admin-side-nav" aria-label="CMS sections">
                        <a class="{{ $navClass('dashboard') }}" href="{{ route('admin.dashboard') }}" data-admin-nav="dashboard">
                            <span class="admin-nav-icon" aria-hidden="true">▦</span>
                            <span>Resumen Principal</span>
                        </a>

                        <p>Tabs publicos</p>

                        <a class="{{ $navClass('contenido') }}" href="{{ route('admin.content.index', ['section' => 'music']) }}" data-admin-nav="contenido">
                            <span class="admin-nav-icon" aria-hidden="true">♫</span>
                            <span>Musica / Content</span>
                        </a>
                        <a class="{{ $navClass('editor') }}" href="{{ route('admin.content.create') }}" data-admin-nav="editor">
                            <span class="admin-nav-icon" aria-hidden="true">✎</span>
                            <span>Nuevo Contenido</span>
                        </a>
                        <a class="{{ $navClass('biblioteca') }}" href="{{ route('admin.media.index') }}" data-admin-nav="biblioteca">
                            <span class="admin-nav-icon" aria-hidden="true">▧</span>
                            <span>Video / Biblioteca</span>
                        </a>
                        <a class="{{ $navClass('productos') }}" href="{{ $sectionRoute('productos') }}" data-admin-nav="productos">
                            <span class="admin-nav-icon" aria-hidden="true">□</span>
                            <span>Productos y Drops</span>
                        </a>

                        <p>Comunidad y Miembros</p>

                        <a class="{{ $navClass('royalpass') }}" href="{{ $sectionRoute('royalpass') }}" data-admin-nav="royalpass">
                            <span class="admin-nav-icon" aria-hidden="true">♛</span>
                            <span>Miembros Royal <small>PRO</small></span>
                        </a>
                        <a class="{{ $navClass('usuarios') }}" href="{{ $sectionRoute('usuarios') }}" data-admin-nav="usuarios">
                            <span class="admin-nav-icon" aria-hidden="true">◎</span>
                            <span>Gente / Usuarios</span>
                        </a>
                        <a class="{{ $navClass('comunidad') }}" href="{{ $sectionRoute('comunidad') }}" data-admin-nav="comunidad">
                            <span class="admin-nav-icon" aria-hidden="true">☷</span>
                            <span>Community</span>
                        </a>
                        <a class="{{ $navClass('eventos') }}" href="{{ $sectionRoute('eventos') }}" data-admin-nav="eventos">
                            <span class="admin-nav-icon" aria-hidden="true">◴</span>
                            <span>Events / Tickets</span>
                        </a>
                        <a class="{{ $navClass('puntos') }}" href="{{ $sectionRoute('puntos') }}" data-admin-nav="puntos">
                            <span class="admin-nav-icon" aria-hidden="true">◇</span>
                            <span>Puntos y Ranking</span>
                        </a>

                        <p>Negocios y Control</p>

                        <a class="{{ $navClass('pagos') }}" href="{{ $sectionRoute('pagos') }}" data-admin-nav="pagos">
                            <span class="admin-nav-icon" aria-hidden="true">$</span>
                            <span>Pagos y Devoluciones</span>
                        </a>
                        <a class="{{ $navClass('notificaciones') }}" href="{{ $sectionRoute('notificaciones') }}" data-admin-nav="notificaciones">
                            <span class="admin-nav-icon" aria-hidden="true">✉</span>
                            <span>Anuncios y Mensajes</span>
                        </a>
                        <a class="{{ $navClass('equipo') }}" href="{{ $sectionRoute('equipo') }}" data-admin-nav="equipo">
                            <span class="admin-nav-icon" aria-hidden="true">◌</span>
                            <span>Equipo y Permisos</span>
                        </a>
                        <a class="{{ $navClass('historial') }}" href="{{ $sectionRoute('historial') }}" data-admin-nav="historial">
                            <span class="admin-nav-icon" aria-hidden="true">◷</span>
                            <span>Historial de Cambios</span>
                        </a>
                        <a class="{{ $navClass('ajustes') }}" href="{{ $sectionRoute('ajustes') }}" data-admin-nav="ajustes">
                            <span class="admin-nav-icon" aria-hidden="true">⚙</span>
                            <span>Ajustes del Sitio</span>
                        </a>
                    </nav>
                </div>

                <div class="admin-sidebar-footer">
                    <p>© 2026 Reny Renteria CMS</p>
                    <span><i></i> Servidores Activos</span>
                    @if ($user)
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button class="admin-button admin-button-secondary" type="submit">Log out</button>
                        </form>
                    @endif
                </div>
            </aside>

            <button id="sidebarOverlay" type="button" class="admin-sidebar-overlay" data-admin-sidebar-toggle aria-label="Cerrar menu"></button>

            <main class="admin-cms-main">
                <nav class="ds-public-tabs" aria-label="Tabs publicos del producto">
                    <a class="ds-main-tab" href="{{ route('admin.content.index', ['section' => 'music']) }}" data-admin-nav="contenido" style="--tab-accent: var(--music-accent); --tab-soft: var(--music-soft);">
                        <span>Musica</span>
                        <span class="ds-tab-dot" aria-hidden="true"></span>
                    </a>
                    <a class="ds-main-tab" href="{{ route('admin.content.index', ['section' => 'video']) }}" data-admin-nav="biblioteca" style="--tab-accent: var(--video-accent); --tab-soft: var(--video-soft);">
                        <span>Video</span>
                        <span class="ds-tab-dot" aria-hidden="true"></span>
                    </a>
                    <a class="ds-main-tab" href="{{ route('admin.content.index', ['section' => 'events']) }}" data-admin-nav="eventos" style="--tab-accent: var(--events-accent); --tab-soft: var(--events-soft);">
                        <span>Events</span>
                        <span class="ds-tab-dot" aria-hidden="true"></span>
                    </a>
                    <a class="ds-main-tab" href="{{ route('admin.content.index', ['section' => 'community']) }}" data-admin-nav="comunidad" style="--tab-accent: var(--community-accent); --tab-soft: var(--community-soft);">
                        <span>Community</span>
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
