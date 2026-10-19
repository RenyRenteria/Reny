<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Official merch') | Reny Renteria</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="yoycol-page">
    <header class="yoycol-header">
        <a href="{{ route('home') }}"><img src="{{ asset('images/reny-renteria-logo-white.png') }}" alt="Reny Renteria" width="150"></a>
        <nav aria-label="Merch navigation"><a href="{{ route('merch') }}">Merch</a><a href="{{ route('account.show') }}">My account</a></nav>
    </header>
    <main class="yoycol-main">@yield('content')</main>
</body>
</html>
