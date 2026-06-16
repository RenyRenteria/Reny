<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Media Library | Reny Renteria</title>

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
                    <a class="is-active" href="{{ route('admin.media.index') }}">Media</a>
                    <a href="{{ route('admin.editorial.index') }}">Content</a>
                    <a href="{{ route('admin.editorial.index') }}#schedule-panel">Schedule</a>
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
                        <h1>Media library</h1>
                    </div>

                    <div class="admin-user">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ str_replace('_', ' ', auth()->user()->role) }}</span>
                    </div>
                </header>

                @if (session('status'))
                    <div class="auth-status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="auth-status" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <section class="admin-panel" aria-labelledby="upload-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">App server</p>
                            <h2 id="upload-title">Upload assets</h2>
                        </div>
                    </div>

                    <form class="admin-form-grid" method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
                        @csrf

                        <label>
                            <span>Type</span>
                            <select name="type" required>
                                @foreach ($types as $type)
                                    <option value="{{ $type->value }}">{{ str_replace('_', ' ', $type->value) }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Title</span>
                            <input name="title" type="text" maxlength="160">
                        </label>

                        <label>
                            <span>Alt text</span>
                            <input name="alt_text" type="text" maxlength="180">
                        </label>

                        <label>
                            <span>Duration seconds</span>
                            <input name="duration_seconds" type="number" min="1" max="{{ config('media.short_video_duration_seconds') }}">
                        </label>

                        <label class="admin-checkbox">
                            <input name="is_public" type="hidden" value="0">
                            <input name="is_public" type="checkbox" value="1" checked>
                            <span>Public asset</span>
                        </label>

                        <label class="admin-file-field">
                            <span>Files</span>
                            <input name="files[]" type="file" multiple required>
                        </label>

                        <button class="admin-button admin-button-primary" type="submit">Upload</button>
                    </form>
                </section>

                <section class="admin-panel" aria-labelledby="mux-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">Mux</p>
                            <h2 id="mux-title">Short video upload</h2>
                        </div>
                    </div>

                    <form class="admin-form-grid" method="POST" action="{{ route('admin.media.mux.direct-uploads.store') }}">
                        @csrf

                        <label>
                            <span>Filename</span>
                            <input name="original_filename" type="text" placeholder="video.mp4" maxlength="255" required>
                        </label>

                        <label>
                            <span>Title</span>
                            <input name="title" type="text" maxlength="160">
                        </label>

                        <label>
                            <span>MIME type</span>
                            <input name="mime_type" type="text" placeholder="video/mp4" maxlength="160" required>
                        </label>

                        <label>
                            <span>Size bytes</span>
                            <input name="size_bytes" type="number" min="1">
                        </label>

                        <label>
                            <span>Duration seconds</span>
                            <input name="duration_seconds" type="number" min="1" max="{{ config('media.short_video_duration_seconds') }}" required>
                        </label>

                        <label class="admin-checkbox">
                            <input name="is_public" type="hidden" value="0">
                            <input name="is_public" type="checkbox" value="1" checked>
                            <span>Public playback</span>
                        </label>

                        <button class="admin-button admin-button-danger" type="submit">Create upload</button>
                    </form>
                </section>

                <section class="admin-panel" aria-labelledby="assets-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">Library</p>
                            <h2 id="assets-title">Recent assets</h2>
                        </div>
                    </div>

                    <div class="admin-media-list">
                        @forelse ($assets as $asset)
                            <article class="admin-media-row">
                                <div>
                                    <span>{{ $asset->type->value }}</span>
                                    <strong>{{ $asset->title ?: $asset->original_filename }}</strong>
                                    <small>{{ $asset->original_filename }}</small>
                                </div>

                                <div class="admin-badges">
                                    <span>{{ $asset->processing_status->value }}</span>
                                    <span>{{ $asset->is_public ? 'public' : 'private' }}</span>
                                    @if ($asset->mux_upload_id)
                                        <span>Mux</span>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="admin-empty-state">No media assets yet.</div>
                        @endforelse
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
