<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.public-seo', [
            'seo' => [
                'meta_title' => data_get($content->metadata, 'meta_title', $content->title.' | Reny Renteria'),
                'meta_description' => data_get($content->metadata, 'meta_description', $content->summary ?? ''),
                'canonical_url' => data_get($content->metadata, 'canonical_url', route('public.content.show', $content)),
                'og_title' => data_get($content->metadata, 'og_title', $content->title),
                'og_description' => data_get($content->metadata, 'og_description', $content->summary ?? ''),
                'og_image' => data_get($content->metadata, 'og_image'),
                'twitter_card' => data_get($content->metadata, 'twitter_card', 'summary_large_image'),
                'twitter_title' => data_get($content->metadata, 'twitter_title', $content->title),
                'twitter_description' => data_get($content->metadata, 'twitter_description', $content->summary ?? ''),
                'twitter_image' => data_get($content->metadata, 'twitter_image'),
            ],
            'fallbackTitle' => $content->title.' | Reny Renteria',
        ])
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
