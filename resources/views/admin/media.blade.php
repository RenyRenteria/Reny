@extends('admin.layout')

@section('title', 'Biblioteca de medios')
@section('admin_section', 'media')
@section('admin_theme', 'video')

@section('content')
    <section class="admin-dashboard-section is-active">
        <div class="admin-page-heading">
            <div>
                <h1>Biblioteca de Fotos y Videos</h1>
                <p>Administra todos los archivos cargados al sitio web.</p>
            </div>
        </div>

        <div class="admin-media-workspace">
            <section class="admin-panel" aria-labelledby="upload-title">
                <div class="admin-section-head">
                    <div>
                        <p class="admin-kicker">App server</p>
                        <h2 id="upload-title">Subir assets</h2>
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
        </div>

        <section class="admin-panel" aria-labelledby="assets-title">
            <div class="admin-section-head">
                <div>
                    <p class="admin-kicker">Library</p>
                    <h2 id="assets-title">Recent assets</h2>
                </div>
            </div>

            <div class="admin-media-grid">
                @forelse ($assets as $asset)
                    <article class="admin-media-card">
                        <div class="admin-media-thumb">{{ strtoupper(str_replace('_', ' ', $asset->type->value)) }}</div>
                        <h3>{{ $asset->title ?: $asset->original_filename }}</h3>
                        <p>{{ $asset->original_filename }} · {{ number_format($asset->size_bytes / 1024, 1) }} KB</p>
                        <footer>
                            <span>{{ $asset->processing_status->value }}</span>
                            <span>{{ $asset->is_public ? 'public' : 'private' }}</span>
                            @if ($asset->mux_upload_id)
                                <span>Mux</span>
                            @endif
                        </footer>
                    </article>
                @empty
                    <div class="admin-empty-state">No media assets yet.</div>
                @endforelse
            </div>
        </section>
    </section>
@endsection
