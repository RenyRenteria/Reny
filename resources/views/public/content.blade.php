<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $content->title }} | Reny Renteria</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="public_content">
        <main class="public-content-shell">
            <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
            </a>

            <article class="public-content-card">
                <span>{{ strtoupper($content->type->value) }} / {{ strtoupper($content->visibility->value) }}</span>
                <h1>{{ $content->title }}</h1>

                @if ($content->summary)
                    <p>{{ $content->summary }}</p>
                @endif

                @if ($content->body)
                    <div class="public-content-body">{!! nl2br(e($content->body)) !!}</div>
                @endif
            </article>
        </main>
    </body>
</html>
