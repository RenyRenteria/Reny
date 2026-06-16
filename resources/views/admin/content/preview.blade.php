<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">

        <title>Preview | {{ $content->title }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="admin-shell">
            <aside class="admin-sidebar" aria-label="Admin navigation">
                <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>

                <nav class="admin-nav" aria-label="CMS sections">
                    <a href="{{ route('admin.dashboard') }}">Overview</a>
                    <a href="{{ route('admin.media.index') }}">Media</a>
                    <a class="is-active" href="{{ route('admin.content.index') }}">Content</a>
                    <a href="{{ route('admin.content.index', ['status' => 'scheduled']) }}">Schedule</a>
                </nav>

                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="admin-button admin-button-secondary" type="submit">Log out</button>
                </form>
            </aside>

            <main class="admin-main">
                <header class="admin-topbar">
                    <div>
                        <p class="admin-kicker">Private preview</p>
                        <h1>{{ $content->title }}</h1>
                    </div>

                    <div class="admin-user">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ str_replace('_', ' ', auth()->user()->role) }}</span>
                    </div>
                </header>

                <section class="admin-panel" aria-labelledby="preview-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">{{ str_replace('_', ' ', $content->type->value) }}</p>
                            <h2 id="preview-title">{{ $content->summary ?: $content->title }}</h2>
                        </div>

                        <div class="admin-actions">
                            <a class="admin-button admin-button-primary" href="{{ route('admin.content.edit', $content) }}">Edit</a>
                            <a class="admin-button admin-button-ghost" href="{{ route('admin.content.index') }}">Content</a>
                        </div>
                    </div>

                    <div class="admin-preview-grid">
                        <article class="admin-preview-body">
                            @if ($content->body)
                                {!! nl2br(e($content->body)) !!}
                            @else
                                <span class="admin-empty-state">No body content.</span>
                            @endif
                        </article>

                        <aside class="admin-preview-meta" aria-label="Content metadata">
                            <div>
                                <span>Status</span>
                                <strong>{{ $content->status->value }}</strong>
                            </div>
                            <div>
                                <span>Visibility</span>
                                <strong>{{ $content->visibility->value }}</strong>
                            </div>
                            <div>
                                <span>Schedule</span>
                                <strong>
                                    {{ $content->scheduled_at ? $content->scheduled_at->copy()->timezone($timezone)->format('M j, Y g:i A') : 'None' }}
                                </strong>
                            </div>
                            <div>
                                <span>Approval</span>
                                <strong>{{ $content->needs_approval ? 'Needs approval' : 'Cleared' }}</strong>
                            </div>
                        </aside>
                    </div>
                </section>

                <section class="admin-panel" aria-labelledby="metadata-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">Schema</p>
                            <h2 id="metadata-title">Metadata</h2>
                        </div>
                    </div>

                    @if ($content->metadata)
                        <dl class="admin-definition-grid">
                            @foreach ($content->metadata as $key => $value)
                                <div>
                                    <dt>{{ str_replace('_', ' ', $key) }}</dt>
                                    <dd>
                                        @if (is_array($value))
                                            {{ json_encode($value) }}
                                        @else
                                            {{ $value }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <div class="admin-empty-state">No metadata.</div>
                    @endif
                </section>

                <section class="admin-panel" aria-labelledby="attached-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">Reuse</p>
                            <h2 id="attached-title">Attached assets</h2>
                        </div>
                    </div>

                    <div class="admin-media-list">
                        @forelse ($content->mediaAssets as $asset)
                            <article class="admin-media-row">
                                <div>
                                    <span>{{ $asset->type->value }}</span>
                                    <strong>{{ $asset->title ?: $asset->original_filename }}</strong>
                                    <small>{{ $asset->is_public ? 'public' : 'private' }}</small>
                                </div>

                                <div class="admin-badges">
                                    <span>{{ $asset->processing_status->value }}</span>
                                    <span>{{ $asset->pivot->role }}</span>
                                </div>
                            </article>
                        @empty
                            <div class="admin-empty-state">No attached assets.</div>
                        @endforelse
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
