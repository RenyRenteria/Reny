<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin | {{ config('app.name', 'Reny') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="admin-body">
        <div class="admin-shell">
            <aside class="admin-sidebar">
                <a href="{{ route('home') }}" class="admin-logo-link">
                    <img src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>
                <nav>
                    <a href="#hero">Hero</a>
                    <a href="#albums">Albums</a>
                    <a href="#singles">Singles</a>
                    <a href="{{ route('home') }}">View site</a>
                </nav>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="admin-ghost-button">Log out</button>
                </form>
            </aside>

            <main class="admin-main">
                <div class="admin-topbar">
                    <div>
                        <p class="admin-kicker">Reny Renteria</p>
                        <h1>Content admin</h1>
                    </div>
                    <a class="admin-secondary-button" href="{{ route('home') }}">Open site</a>
                </div>

                @if (session('status'))
                    <div class="admin-status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="admin-error-list">
                        <strong>Fix these fields:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="admin-panel" id="hero">
                    <div class="admin-panel-head">
                        <div>
                            <p class="admin-kicker">Featured area</p>
                            <h2>Hero content</h2>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.hero.update') }}" enctype="multipart/form-data" class="admin-grid-form">
                        @csrf
                        @method('PUT')

                        <label>
                            Eyebrow
                            <textarea name="eyebrow" rows="2">{{ old('eyebrow', $hero->eyebrow) }}</textarea>
                        </label>
                        <label>
                            Main title
                            <textarea name="title" rows="2" required>{{ old('title', $hero->title) }}</textarea>
                        </label>
                        <label>
                            Subtitle
                            <input name="subtitle" value="{{ old('subtitle', $hero->subtitle) }}">
                        </label>
                        <label>
                            Link text
                            <textarea name="link_text" rows="2">{{ old('link_text', $hero->link_text) }}</textarea>
                        </label>
                        <label class="admin-span-2">
                            Body
                            <textarea name="body" rows="3">{{ old('body', $hero->body) }}</textarea>
                        </label>
                        <label>
                            Artwork badge
                            <input name="badge_text" value="{{ old('badge_text', $hero->badge_text) }}">
                        </label>
                        <label>
                            Hero artwork
                            <input name="hero_image" type="file" accept="image/*">
                        </label>

                        @if ($hero->image_path)
                            <label class="admin-checkbox">
                                <input type="checkbox" name="remove_image" value="1">
                                Remove current artwork
                            </label>
                        @endif

                        <div class="admin-form-actions admin-span-2">
                            <button type="submit" class="admin-primary-button">Save hero</button>
                        </div>
                    </form>
                </section>

                <section class="admin-panel" id="albums">
                    <div class="admin-panel-head">
                        <div>
                            <p class="admin-kicker">Music catalog</p>
                            <h2>Albums</h2>
                        </div>
                    </div>

                    <div class="admin-items">
                        @forelse ($albums as $album)
                            <article class="admin-item">
                                <form method="POST" action="{{ route('admin.albums.update', $album) }}" enctype="multipart/form-data" class="admin-grid-form">
                                    @csrf
                                    @method('PUT')

                                    <label>
                                        Title
                                        <input name="title" value="{{ old('title', $album->title) }}" required>
                                    </label>
                                    <label>
                                        Tracks
                                        <input name="track_count" type="number" min="0" max="999" value="{{ old('track_count', $album->track_count) }}">
                                    </label>
                                    <label>
                                        Cover label
                                        <input name="cover_label" value="{{ old('cover_label', $album->cover_label) }}">
                                    </label>
                                    <label>
                                        Sort
                                        <input name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', $album->sort_order) }}">
                                    </label>
                                    <label>
                                        Cover image
                                        <input name="image" type="file" accept="image/*">
                                    </label>
                                    <label class="admin-checkbox">
                                        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $album->is_published))>
                                        Published
                                    </label>
                                    @if ($album->image_path)
                                        <label class="admin-checkbox">
                                            <input type="checkbox" name="remove_image" value="1">
                                            Remove image
                                        </label>
                                    @endif
                                    <div class="admin-form-actions admin-span-2">
                                        <button type="submit" class="admin-primary-button">Save album</button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('admin.albums.destroy', $album) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-danger-button">Delete album</button>
                                </form>
                            </article>
                        @empty
                            <p class="admin-empty">No albums saved yet. Add the first one below.</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('admin.albums.store') }}" enctype="multipart/form-data" class="admin-create-form">
                        @csrf
                        <h3>Add album</h3>
                        <div class="admin-grid-form">
                            <label>
                                Title
                                <input name="title" required>
                            </label>
                            <label>
                                Tracks
                                <input name="track_count" type="number" min="0" max="999" value="0">
                            </label>
                            <label>
                                Cover label
                                <input name="cover_label">
                            </label>
                            <label>
                                Sort
                                <input name="sort_order" type="number" min="0" max="999" value="0">
                            </label>
                            <label>
                                Cover image
                                <input name="image" type="file" accept="image/*">
                            </label>
                            <label class="admin-checkbox">
                                <input type="checkbox" name="is_published" value="1" checked>
                                Published
                            </label>
                            <div class="admin-form-actions admin-span-2">
                                <button type="submit" class="admin-primary-button">Add album</button>
                            </div>
                        </div>
                    </form>
                </section>

                <section class="admin-panel" id="singles">
                    <div class="admin-panel-head">
                        <div>
                            <p class="admin-kicker">Track list</p>
                            <h2>Singles</h2>
                        </div>
                    </div>

                    <div class="admin-items">
                        @forelse ($singles as $single)
                            <article class="admin-item">
                                <form method="POST" action="{{ route('admin.singles.update', $single) }}" enctype="multipart/form-data" class="admin-grid-form">
                                    @csrf
                                    @method('PUT')

                                    <label>
                                        Title
                                        <input name="title" value="{{ old('title', $single->title) }}" required>
                                    </label>
                                    <label>
                                        Artist
                                        <input name="artist" value="{{ old('artist', $single->artist) }}">
                                    </label>
                                    <label>
                                        Audio URL
                                        <input name="audio_url" type="url" value="{{ old('audio_url', $single->audio_url) }}">
                                    </label>
                                    <label>
                                        Audio file
                                        <input name="audio_file" type="file" accept="audio/*">
                                    </label>
                                    <label>
                                        Sort
                                        <input name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', $single->sort_order) }}">
                                    </label>
                                    <label>
                                        Artwork
                                        <input name="image" type="file" accept="image/*">
                                    </label>
                                    <label class="admin-checkbox">
                                        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $single->is_published))>
                                        Published
                                    </label>
                                    @if ($single->image_path)
                                        <label class="admin-checkbox">
                                            <input type="checkbox" name="remove_image" value="1">
                                            Remove image
                                        </label>
                                    @endif
                                    @if ($single->audio_path)
                                        <label class="admin-checkbox">
                                            <input type="checkbox" name="remove_audio" value="1">
                                            Remove audio file
                                        </label>
                                    @endif
                                    <div class="admin-form-actions admin-span-2">
                                        <button type="submit" class="admin-primary-button">Save single</button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('admin.singles.destroy', $single) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-danger-button">Delete single</button>
                                </form>
                            </article>
                        @empty
                            <p class="admin-empty">No singles saved yet. Add the first one below.</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('admin.singles.store') }}" enctype="multipart/form-data" class="admin-create-form">
                        @csrf
                        <h3>Add single</h3>
                        <div class="admin-grid-form">
                            <label>
                                Title
                                <input name="title" required>
                            </label>
                            <label>
                                Artist
                                <input name="artist" value="Reny Renteria">
                            </label>
                            <label>
                                Audio URL
                                <input name="audio_url" type="url">
                            </label>
                            <label>
                                Audio file
                                <input name="audio_file" type="file" accept="audio/*">
                            </label>
                            <label>
                                Sort
                                <input name="sort_order" type="number" min="0" max="999" value="0">
                            </label>
                            <label>
                                Artwork
                                <input name="image" type="file" accept="image/*">
                            </label>
                            <label class="admin-checkbox">
                                <input type="checkbox" name="is_published" value="1" checked>
                                Published
                            </label>
                            <div class="admin-form-actions admin-span-2">
                                <button type="submit" class="admin-primary-button">Add single</button>
                            </div>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    </body>
</html>
