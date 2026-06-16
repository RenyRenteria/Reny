<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow,noarchive">

        <title>Private preview | {{ $content->title }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="admin-preview-shell">
            <nav class="admin-preview-toolbar" aria-label="Preview actions">
                <a class="admin-button admin-button-secondary" href="{{ route('admin.editorial.edit', $content) }}">Back to edit</a>
                <span>Private preview / noindex</span>
            </nav>

            <article class="admin-preview-article">
                <header>
                    <p class="admin-kicker">{{ str_replace('_', ' ', $content->type->value) }} / {{ $content->status->value }}</p>
                    <h1>{{ $content->title }}</h1>

                    @if ($content->summary)
                        <p>{{ $content->summary }}</p>
                    @endif

                    <div class="admin-badges">
                        <span>{{ $content->visibility->value }}</span>
                        @if ($content->scheduled_at)
                            <span>{{ $content->scheduled_at->timezone($panamaTimezone)->format('M j, Y g:i A') }} Panama</span>
                        @endif
                        @if ($content->needs_approval)
                            <span>needs approval</span>
                        @endif
                    </div>
                </header>

                @if ($content->mediaAssets->isNotEmpty())
                    <section class="admin-preview-section" aria-labelledby="preview-media-title">
                        <h2 id="preview-media-title">Media</h2>

                        <div class="admin-media-list">
                            @foreach ($content->mediaAssets as $asset)
                                <article class="admin-media-row">
                                    <div>
                                        <span>{{ $asset->pivot->role }}</span>
                                        <strong>{{ $asset->title ?: $asset->original_filename }}</strong>
                                        <small>{{ $asset->type->value }} / {{ $asset->processing_status->value }}</small>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($content->body)
                    <section class="admin-preview-section" aria-labelledby="preview-body-title">
                        <h2 id="preview-body-title">Body</h2>
                        <div class="admin-preview-copy">{{ $content->body }}</div>
                    </section>
                @endif

                @if ($content->metadata)
                    <section class="admin-preview-section" aria-labelledby="preview-metadata-title">
                        <h2 id="preview-metadata-title">Metadata</h2>

                        <dl class="admin-preview-metadata">
                            @foreach ($content->metadata as $key => $value)
                                <div>
                                    <dt>{{ str_replace('_', ' ', $key) }}</dt>
                                    <dd>
                                        @if (is_array($value))
                                            {{ implode(', ', $value) }}
                                        @else
                                            {{ $value }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                @if ($content->releaseWindows->isNotEmpty())
                    <section class="admin-preview-section" aria-labelledby="preview-windows-title">
                        <h2 id="preview-windows-title">Release windows</h2>

                        <div class="admin-media-list">
                            @foreach ($content->releaseWindows as $window)
                                <article class="admin-media-row">
                                    <div>
                                        <span>{{ $window->audience->value }}</span>
                                        <strong>
                                            {{ $window->starts_at?->timezone($panamaTimezone)->format('M j, Y g:i A') ?? 'Always open' }}
                                        </strong>
                                        <small>
                                            Ends {{ $window->ends_at?->timezone($panamaTimezone)->format('M j, Y g:i A') ?? 'without end date' }}
                                        </small>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </article>
        </main>
    </body>
</html>
