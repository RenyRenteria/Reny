<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Content CMS | Reny Renteria</title>

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
                    <a @class(['is-active' => $activeStatus === 'scheduled']) href="{{ route('admin.content.index', ['status' => 'scheduled']) }}">Schedule</a>
                </nav>

                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="admin-button admin-button-secondary" type="submit">Log out</button>
                </form>
            </aside>

            <main class="admin-main">
                <header class="admin-topbar">
                    <div>
                        <p class="admin-kicker">Project 3 CMS</p>
                        <h1>Content workspace</h1>
                    </div>

                    <div class="admin-user">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ str_replace('_', ' ', auth()->user()->role) }}</span>
                    </div>
                </header>

                @if (session('status'))
                    <div class="auth-status">{{ session('status') }}</div>
                @endif

                <section class="admin-panel" aria-labelledby="create-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">Forms</p>
                            <h2 id="create-title">Create content</h2>
                        </div>
                    </div>

                    <div class="admin-type-grid">
                        @foreach ($contentTypes as $type)
                            <a class="admin-type-link" href="{{ route('admin.content.create', ['type' => $type->value]) }}">
                                {{ str_replace('_', ' ', $type->value) }}
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="admin-panel" aria-labelledby="filters-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">Pipeline</p>
                            <h2 id="filters-title">Content queue</h2>
                        </div>
                    </div>

                    <div class="admin-filter-row">
                        <a @class(['is-active' => $activeStatus === null]) href="{{ route('admin.content.index') }}">All</a>
                        @foreach ($statuses as $status)
                            <a @class(['is-active' => $activeStatus === $status->value]) href="{{ route('admin.content.index', ['status' => $status->value]) }}">
                                {{ $status->value }}
                            </a>
                        @endforeach
                    </div>

                    <div class="admin-queue">
                        @forelse ($contents as $content)
                            <article class="admin-queue-item">
                                <div>
                                    <span>{{ str_replace('_', ' ', $content->type->value) }}</span>
                                    <strong>{{ $content->title }}</strong>
                                    <small class="admin-row-note">
                                        @if ($content->scheduled_at)
                                            {{ $content->scheduled_at->copy()->timezone($timezone)->format('M j, Y g:i A') }} {{ $timezone }}
                                        @else
                                            {{ $content->created_at->format('M j, Y g:i A') }}
                                        @endif
                                    </small>
                                </div>

                                <div class="admin-row-actions">
                                    <div class="admin-badges">
                                        <span>{{ $content->status->value }}</span>
                                        <span>{{ $content->visibility->value }}</span>
                                        @if ($content->needs_approval)
                                            <span>needs approval</span>
                                        @endif
                                    </div>

                                    <div class="admin-actions">
                                        <a class="admin-button admin-button-primary" href="{{ route('admin.content.edit', $content) }}">Edit</a>
                                        <a class="admin-button admin-button-ghost" href="{{ route('admin.content.preview', $content) }}">Preview</a>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="admin-empty-state">No content yet.</div>
                        @endforelse
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
