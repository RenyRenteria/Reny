<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    @include('partials.google-analytics')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Official merch') | Reny Renteria</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="reny-site yoycol-page">
    <x-public-navigation />
    <main class="yoycol-main">
                <span id="site-content" class="sr-only" tabindex="-1"></span>@yield('content')</main>
            <x-public-footer />
</body>
</html>
