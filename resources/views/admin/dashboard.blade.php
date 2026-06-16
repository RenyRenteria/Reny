<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Admin CMS | Reny Renteria</title>

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
                    <a class="is-active" href="{{ route('admin.dashboard') }}">Overview</a>
                    <a href="{{ route('admin.media.index') }}">Media</a>
                    <a href="{{ route('admin.content.index') }}">Content</a>
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
                        <p class="admin-kicker">Project 3 CMS</p>
                        <h1>Admin workspace</h1>
                    </div>

                    <div class="admin-user">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ str_replace('_', ' ', auth()->user()->role) }}</span>
                    </div>
                </header>

                @if (session('status'))
                    <div class="auth-status">{{ session('status') }}</div>
                @endif

                <section class="admin-grid" aria-label="Admin status">
                    <article class="admin-metric">
                        <span>Role</span>
                        <strong>{{ auth()->user()->canPublishContent() ? 'Publisher' : 'Editor' }}</strong>
                    </article>
                    <article class="admin-metric">
                        <span>Publishing</span>
                        <strong>{{ $canPublish ? 'Allowed' : 'Approval required' }}</strong>
                    </article>
                    <article class="admin-metric">
                        <span>Session</span>
                        <strong>Active</strong>
                    </article>
                </section>

                <section class="admin-panel" aria-labelledby="queue-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">Editorial queue</p>
                            <h2 id="queue-title">Review pipeline</h2>
                        </div>
                    </div>

                    <div class="admin-queue">
                        @foreach ($queueItems as $item)
                            <article class="admin-queue-item">
                                <div>
                                    <span>{{ $item['type'] }}</span>
                                    <strong>{{ $item['title'] }}</strong>
                                </div>

                                <div class="admin-badges">
                                    <span>{{ $item['status'] }}</span>
                                    @if ($item['needsApproval'])
                                        <span>needs approval</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="admin-panel" aria-labelledby="actions-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">RBAC check</p>
                            <h2 id="actions-title">Editorial actions</h2>
                        </div>
                    </div>

                    <div class="admin-actions">
                        <form method="POST" action="{{ route('admin.editorial.drafts.store') }}">
                            @csrf
                            <input name="title" type="hidden" value="Project 3 CMS readiness">
                            <button class="admin-button admin-button-primary" type="submit">Save draft</button>
                        </form>

                        <form method="POST" action="{{ route('admin.editorial.publish') }}">
                            @csrf
                            <input name="title" type="hidden" value="Project 3 CMS readiness">
                            <button class="admin-button admin-button-danger" type="submit" @disabled(! $canPublish)>Publish</button>
                        </form>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
