@php
    use App\Enums\PhotoVisibility;

    $maxMb = number_format($limits['max_file_kb'] / 1024);
@endphp

@extends('admin.layout')

@section('title', 'Photos CMS')
@section('admin_section', 'photos')
@section('admin_theme', 'events')

@section('content')
    <section class="admin-dashboard-section is-active photos-cms-screen" data-photo-cms>
        <div class="admin-page-heading">
            <div>
                <h1>Photos</h1>
                <p>Carga, organiza y protege fotos publicas o solo para Royal Pass.</p>
            </div>
            <a class="admin-button admin-button-ghost" href="{{ url('/photos') }}" target="_blank" rel="noreferrer">Ver website</a>
        </div>

        <div class="photos-cms-grid">
            <section class="admin-panel photo-upload-panel" aria-labelledby="photo-upload-title">
                <div class="music-banner-panel-head">
                    <div>
                        <p class="admin-kicker">Uploader</p>
                        <h2 id="photo-upload-title">Nuevo carrete</h2>
                    </div>
                    <span>{{ $maxMb }}MB / foto</span>
                </div>

                <form
                    class="photo-upload-form"
                    action="{{ route('cms.photos.upload') }}"
                    method="POST"
                    enctype="multipart/form-data"
                    data-photo-upload-form
                >
                    @csrf

                    <div class="admin-form-grid admin-form-grid-wide">
                        <label>
                            <span>Titulo del album</span>
                            <input name="album_title" type="text" maxlength="160" placeholder="Ej: Backstage Junio">
                        </label>

                        <label>
                            <span>Descripcion</span>
                            <input name="album_description" type="text" maxlength="500" placeholder="Contexto del carrete">
                        </label>
                    </div>

                    <label class="photo-dropzone" data-photo-dropzone>
                        <input
                            name="files[]"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            data-photo-file-input
                        >
                        <span>Arrastra fotos aqui o selecciona archivos</span>
                        <small>JPG, PNG o WEBP. Batch grande: mas de {{ $limits['large_batch_threshold'] }} fotos pasa a processing.</small>
                    </label>

                    <div class="photo-upload-preview-grid" data-photo-preview-grid></div>

                    <div class="photo-upload-progress" data-photo-upload-progress hidden>
                        <span data-photo-upload-progress-label>Uploading...</span>
                        <div><i data-photo-upload-progress-bar></i></div>
                    </div>

                    <p class="photo-upload-status" data-photo-upload-status role="status" aria-live="polite"></p>

                    <div class="admin-form-actions">
                        <button class="admin-button admin-button-primary" type="submit">Subir fotos</button>
                    </div>
                </form>
            </section>

            <section class="admin-panel photo-album-panel" aria-labelledby="photo-albums-title">
                <div class="music-banner-panel-head">
                    <div>
                        <p class="admin-kicker">Carretes</p>
                        <h2 id="photo-albums-title">Acciones rapidas</h2>
                    </div>
                    <span>{{ $albums->count() }} albums</span>
                </div>

                <div class="photo-album-list">
                    <form class="admin-form-grid" method="POST" action="{{ route('admin.photos.albums.store') }}">
                        @csrf
                        <label><span>Nuevo album</span><input name="title" maxlength="160" required placeholder="Titulo"></label>
                        <label><span>Descripcion</span><input name="description" maxlength="500"></label>
                        <label><span>Orden</span><input name="order_index" type="number" min="0" value="{{ $albums->count() }}"></label>
                        <button class="admin-button admin-button-primary" type="submit">Crear album</button>
                    </form>

                    @forelse ($albums as $album)
                        <article class="photo-album-row">
                            <form class="admin-form-grid" method="POST" action="{{ route('admin.photos.albums.update', $album) }}">
                                @csrf
                                @method('PATCH')
                                <label><span>Titulo</span><input name="title" value="{{ $album->title }}" maxlength="160" required></label>
                                <label><span>Descripcion</span><input name="description" value="{{ $album->description }}" maxlength="500"></label>
                                <label><span>Orden</span><input name="order_index" type="number" min="0" value="{{ $album->order_index }}" required></label>
                                <label>
                                    <span>Portada</span>
                                    <select name="cover_photo_id">
                                        <option value="">Sin portada</option>
                                        @foreach ($album->photos as $albumPhoto)
                                            <option value="{{ $albumPhoto->id }}" @selected($album->cover_photo_id === $albumPhoto->id)>
                                                #{{ $albumPhoto->id }} · {{ $albumPhoto->caption ?: data_get($albumPhoto->metadata, 'original_filename', 'Photo') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <button class="admin-button admin-button-soft" type="submit">Guardar album</button>
                                <small>{{ $album->photos_count }} fotos</small>
                            </form>
                            <form method="POST" action="{{ route('admin.photos.batch') }}">
                                @csrf
                                <input type="hidden" name="album_id" value="{{ $album->id }}">
                                <button class="admin-button admin-button-soft" type="submit" name="action" value="mark_public">Publicar todo</button>
                                <button class="admin-button admin-button-ghost" type="submit" name="action" value="mark_member_only">Marcar miembro</button>
                            </form>
                            <form method="POST" action="{{ route('admin.photos.albums.destroy', $album) }}">
                                @csrf
                                @method('DELETE')
                                @if ($album->photos_count > 0)
                                    <label>
                                        <span>Reasignar antes de eliminar</span>
                                        <select name="reassign_album_id" required>
                                            <option value="">Selecciona album</option>
                                            @foreach ($albums->where('id', '!=', $album->id) as $targetAlbum)
                                                <option value="{{ $targetAlbum->id }}">{{ $targetAlbum->title }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                                <button class="admin-button admin-button-danger" type="submit" onclick="return confirm('Eliminar album y conservar/reasignar sus fotos?')">Eliminar album</button>
                            </form>
                        </article>
                    @empty
                        <div class="music-manage-empty">Todavia no hay albums nuevos.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="admin-panel photo-manage-panel" aria-labelledby="photo-manage-title">
            <div class="music-banner-panel-head">
                <div>
                    <p class="admin-kicker">Manage Photos</p>
                    <h2 id="photo-manage-title">Biblioteca</h2>
                </div>
                <span>{{ $photos->count() }} fotos</span>
            </div>

            <form class="photo-filters" method="GET" action="{{ route('admin.photos.index') }}">
                <label>
                    <span>Album</span>
                    <select name="album">
                        <option value="">Todos</option>
                        <option value="none" @selected($filters['album'] === 'none')>Fotos sueltas</option>
                        @foreach ($albums as $album)
                            <option value="{{ $album->id }}" @selected($filters['album'] === (string) $album->id)>{{ $album->title }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Visibilidad</span>
                    <select name="visibility">
                        <option value="">Todas</option>
                        @foreach ($visibilities as $visibility)
                            <option value="{{ $visibility->value }}" @selected($filters['visibility'] === $visibility->value)>{{ $visibility->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Estado</span>
                    <select name="status">
                        <option value="">Todos</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Fecha</span>
                    <input name="date" type="date" value="{{ $filters['date'] }}">
                </label>

                <button class="admin-button admin-button-primary" type="submit">Filtrar</button>
                <a class="admin-button admin-button-ghost" href="{{ route('admin.photos.index') }}">Limpiar</a>
            </form>

            <div class="photo-manage-toolbar">
                <form id="photoBatchForm" method="POST" action="{{ route('admin.photos.batch') }}">
                    @csrf
                    <button class="admin-button admin-button-soft" type="submit" name="action" value="mark_public">Publicar seleccion</button>
                    <button class="admin-button admin-button-ghost" type="submit" name="action" value="mark_member_only">Marcar miembro</button>
                    <button class="admin-button admin-button-danger" type="submit" name="action" value="delete" onclick="return confirm('Eliminar fotos seleccionadas y sus archivos?')">Eliminar seleccion</button>
                </form>

                <form id="photoReorderForm" method="POST" action="{{ route('admin.photos.reorder') }}">
                    @csrf
                    <button class="admin-button admin-button-primary" type="submit">Guardar orden</button>
                </form>
            </div>

            <div class="photo-manage-list" data-photo-sort-list>
                @forelse ($photos as $photo)
                    @php
                        $previewUrl = $photo->visibility === PhotoVisibility::MemberOnly
                            ? $photo->blurredUrl()
                            : $photo->thumbnailUrl();
                        $isLegacy = (bool) data_get($photo->metadata, 'legacy_import', false);
                    @endphp

                    <article class="photo-manage-item" data-photo-row data-photo-id="{{ $photo->id }}" draggable="true">
                        <div class="photo-manage-select">
                            <input form="photoBatchForm" type="checkbox" name="photo_ids[]" value="{{ $photo->id }}" aria-label="Seleccionar foto {{ $photo->id }}">
                            <button type="button" class="photo-drag-handle" aria-label="Reordenar">::</button>
                            <input form="photoReorderForm" type="hidden" name="order[{{ $photo->id }}]" value="{{ $photo->order_index }}" data-photo-order-field>
                        </div>

                        <div class="photo-manage-thumb">
                            @if ($previewUrl)
                                <img src="{{ $previewUrl }}" alt="">
                            @else
                                <span>Processing</span>
                            @endif
                            <small>{{ $photo->visibility->label() }}</small>
                        </div>

                        <form class="photo-manage-form" method="POST" action="{{ route('admin.photos.update', $photo) }}">
                            @csrf
                            @method('PATCH')

                            <label class="photo-field-wide">
                                <span>Caption</span>
                                <input name="caption" type="text" maxlength="500" value="{{ $photo->caption }}">
                            </label>

                            <label>
                                <span>Album</span>
                                <select name="album_id">
                                    <option value="">Foto suelta</option>
                                    @foreach ($albums as $album)
                                        <option value="{{ $album->id }}" @selected($photo->album_id === $album->id)>{{ $album->title }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label>
                                <span>Visibilidad</span>
                                <select name="visibility">
                                    @foreach ($visibilities as $visibility)
                                        <option value="{{ $visibility->value }}" @selected($photo->visibility === $visibility)>{{ $visibility->label() }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label>
                                <span>Estado</span>
                                <select name="status">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}" @selected($photo->status === $status)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label>
                                <span>Orden</span>
                                <input name="order_index" type="number" min="0" value="{{ $photo->order_index }}">
                            </label>

                            <div class="photo-manage-meta">
                                <span>{{ $photo->album?->title ?? 'Suelta' }}</span>
                                <span>{{ $photo->status->label() }}</span>
                                @if ($isLegacy)
                                    <span>Instagram legacy</span>
                                @endif
                            </div>

                            <div class="photo-manage-actions">
                                <button class="admin-button admin-button-primary" type="submit">Guardar</button>
                            </div>
                        </form>

                        <form class="photo-delete-form" method="POST" action="{{ route('admin.photos.destroy', $photo) }}" onsubmit="return confirm('Eliminar esta foto y sus archivos?')">
                            @csrf
                            @method('DELETE')
                            <button class="admin-button admin-button-danger" type="submit">Eliminar</button>
                        </form>
                    </article>
                @empty
                    <div class="music-manage-empty">No hay fotos con estos filtros.</div>
                @endforelse
            </div>
        </section>
    </section>
@endsection
