@php
    $adminSection = trim($__env->yieldContent('admin_section', 'dashboard'));
    $adminTheme = trim($__env->yieldContent('admin_theme', 'neutral'));
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
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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

        @include('admin.partials.header', ['showSidebarToggle' => false, 'adminSection' => $adminSection])

        <div class="admin-cms-frame">
            <main class="admin-cms-main">
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
