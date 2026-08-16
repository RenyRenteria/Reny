@php
    $groups = $videoContentForm['groups'];
    $featured = $videoContentForm['featured'];
    $youtubeId = static function (?\App\Models\EditorialContent $content): ?string {
        $url = (string) data_get($content?->metadata, 'youtube_url', '');

        return preg_match('/(?:v=|youtu\.be\/|shorts\/|embed\/)([A-Za-z0-9_-]{6,20})/', $url, $matches) === 1
            ? $matches[1]
            : null;
    };
    $featuredYoutubeId = $youtubeId($featured);
@endphp

<div class="video-cms" data-video-cms data-video-initial-tab="{{ $errors->any() ? old('_video_editor_tab', 'catalog') : 'catalog' }}">
    <div class="admin-page-heading video-cms-heading">
        <div>
            <p class="admin-kicker">Content Management System</p>
            <h1>Videos</h1>
            <p>Administra el destacado, los videos de YouTube y las series que alimentan renyrenteria.com/videos.</p>
        </div>
        <div class="admin-actions">
            <span class="video-cms-connection">CMS conectado</span>
            <a class="admin-button admin-button-ghost" href="{{ $publicUrl }}" target="_blank" rel="noreferrer">Ver website</a>
        </div>
    </div>

    <nav class="video-cms-tabs" role="tablist" aria-label="Herramientas de Videos">
        <button class="video-cms-tab" type="button" role="tab" data-video-tab="catalog">Organizar catálogo</button>
        <button class="video-cms-tab" type="button" role="tab" data-video-tab="add-video">Agregar video</button>
        <button class="video-cms-tab" type="button" role="tab" data-video-tab="add-playlist">Agregar playlist</button>
        <button class="video-cms-tab" type="button" role="tab" data-video-tab="banner">Banner y destacado</button>
    </nav>

    <section class="video-cms-panel" data-video-panel="catalog">
        <div class="video-cms-catalog-layout">
            <div class="video-cms-catalog-main">
                <div class="video-cms-logic admin-panel">
                    <span aria-hidden="true">i</span>
                    <div>
                        <strong>La estructura del CMS refleja la estructura del website</strong>
                        <p>Cada video vive en una sola colección. Las playlists siempre aparecen en Series y el destacado no cambia el orden de las colecciones.</p>
                    </div>
                </div>

                <div class="video-cms-category-grid" aria-label="Filtros rápidos por agrupación">
                    <button class="video-cms-category is-active" type="button" data-video-category-filter="all" style="--video-group-color:#d83a52">
                        <span>Todo el catálogo</span>
                        <strong>{{ $videoContentForm['catalogContents']->count() }}</strong>
                        <small>5 agrupaciones</small>
                    </button>
                    @foreach ($groups as $groupKey => $group)
                        @php
                            $count = ($videoContentForm['grouped']->get($groupKey) ?? collect())->count();
                        @endphp
                        <button class="video-cms-category" type="button" data-video-category-filter="{{ $groupKey }}" style="--video-group-color:{{ $group['color'] }}">
                            <span>{{ $group['label'] }}</span>
                            <strong>{{ $count }}</strong>
                            <small>{{ $group['kind'] }}</small>
                        </button>
                    @endforeach
                </div>

                <article class="video-cms-featured admin-panel">
                    @if ($featured && $featuredYoutubeId)
                        <img src="https://i.ytimg.com/vi/{{ $featuredYoutubeId }}/hqdefault.jpg" alt="Miniatura de {{ $featured->title }}">
                    @else
                        <div class="video-cms-thumb-empty">Sin destacado</div>
                    @endif
                    <div>
                        <span>Video destacado actual</span>
                        <h2>{{ $featured?->title ?? 'Sin video destacado' }}</h2>
                        <p>Se muestra en el hero sin alterar su colección ni su posición.</p>
                    </div>
                    <button class="admin-button admin-button-soft" type="button" data-video-open-tab="banner">Modificar destacado</button>
                </article>

                <div class="video-cms-toolbar admin-panel">
                    <label class="video-cms-search">
                        <span class="sr-only">Buscar videos</span>
                        <input type="search" placeholder="Buscar por título o descripción" data-video-search>
                    </label>
                    <label>
                        <span class="sr-only">Categoría</span>
                        <select data-video-category-select>
                            <option value="all">Todas las agrupaciones</option>
                            @foreach ($groups as $groupKey => $group)
                                <option value="{{ $groupKey }}">{{ $group['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="sr-only">Estado</span>
                        <select data-video-status-select>
                            <option value="all">Todos los estados</option>
                            <option value="published">Publicados</option>
                            <option value="draft">Borradores</option>
                            <option value="scheduled">Programados</option>
                        </select>
                    </label>
                    <button class="admin-button admin-button-primary" type="button" data-video-open-tab="add-video">+ Agregar video</button>
                </div>

                <div class="video-cms-groups" data-video-groups>
                    @foreach ($groups as $groupKey => $group)
                        @php
                            $contents = $videoContentForm['grouped']->get($groupKey) ?? collect();
                        @endphp
                        <section class="video-cms-group admin-panel" data-video-group="{{ $groupKey }}" style="--video-group-color:{{ $group['color'] }}">
                            <header>
                                <div>
                                    <h2>{{ $group['label'] }}</h2>
                                    <span>{{ $contents->count() }} {{ str($group['kind'])->lower() }}</span>
                                </div>
                                <small>Arrastra para ordenar · lo superior aparece primero</small>
                            </header>
                            <div class="video-cms-list" data-video-sort-list="{{ $groupKey }}">
                                @forelse ($contents as $content)
                                    @php
                                        $contentYoutubeId = $youtubeId($content);
                                        $status = $content->status->value;
                                        $searchValue = str($content->title.' '.$content->summary)->lower();
                                    @endphp
                                    <article
                                        class="video-cms-row"
                                        draggable="true"
                                        data-video-row
                                        data-video-id="{{ $content->id }}"
                                        data-video-category="{{ $groupKey }}"
                                        data-video-status="{{ $status }}"
                                        data-video-search-value="{{ $searchValue }}"
                                    >
                                        <input form="video-cms-order-form" type="hidden" name="video_ids[]" value="{{ $content->id }}" data-video-order-input>
                                        <button class="video-cms-drag" type="button" aria-label="Mover {{ $content->title }}">⋮⋮</button>
                                        <div class="video-cms-thumb">
                                            @if ($contentYoutubeId)
                                                <img src="https://i.ytimg.com/vi/{{ $contentYoutubeId }}/hqdefault.jpg" alt="" loading="lazy">
                                            @else
                                                <span>Sin miniatura</span>
                                            @endif
                                        </div>
                                        <div class="video-cms-row-copy">
                                            <strong>{{ $content->title }}</strong>
                                            <span>{{ $content->summary ?: $group['label'] }}</span>
                                            @if (\App\Support\VideoCatalog::isFeatured($content))
                                                <small>Destacado</small>
                                            @endif
                                        </div>
                                        <span class="video-cms-status is-{{ $status }}">{{ str($status)->headline() }}</span>
                                        <details class="video-cms-edit">
                                            <summary class="admin-button admin-button-ghost">Editar</summary>
                                            <div class="video-cms-edit-panel">
                                                @include('admin.site-editor.partials.video-form', [
                                                    'video' => $content,
                                                    'mode' => $groupKey === 'series' ? 'playlist' : 'video',
                                                ])
                                                @if ($content->status === \App\Enums\EditorialStatus::Draft)
                                                    <form method="POST" action="{{ route('admin.content.destroy', $content) }}" data-video-destructive-form>
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="return_to_video_editor" value="1">
                                                        <button class="admin-button admin-button-danger" type="submit">Eliminar borrador</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('admin.content.archive', $content) }}" data-video-destructive-form>
                                                        @csrf
                                                        <button class="admin-button admin-button-danger" type="submit">Archivar</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </details>
                                    </article>
                                @empty
                                    <div class="video-cms-empty">Todavía no hay contenido en esta colección.</div>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>

                <form id="video-cms-order-form" method="POST" action="{{ route('admin.site-editor.videos.order') }}" class="video-cms-order-actions">
                    @csrf
                    <span data-video-order-status>El orden actual está sincronizado con el website.</span>
                    <button class="admin-button admin-button-primary" type="submit" @disabled(! auth()->user()->canPublishContent())>Guardar orden</button>
                </form>
            </div>

            <aside class="video-cms-aside">
                <section class="admin-panel">
                    <h2>Contrato de publicación</h2>
                    <ol>
                        <li><b>1</b><span>Pega una URL válida de YouTube.</span></li>
                        <li><b>2</b><span>Asigna una única agrupación pública.</span></li>
                        <li><b>3</b><span>Previsualiza antes de publicar.</span></li>
                        <li><b>4</b><span>Ordena cada colección por separado.</span></li>
                    </ol>
                </section>
                <section class="admin-panel">
                    <h2>Estado del catálogo</h2>
                    <div class="video-cms-summary">
                        <div><strong>{{ $videoContentForm['publishedCount'] }}</strong><span>Publicados</span></div>
                        <div><strong>{{ $videoContentForm['draftCount'] }}</strong><span>Borradores</span></div>
                        <div><strong>{{ $videoContentForm['scheduledCount'] }}</strong><span>Programados</span></div>
                    </div>
                </section>
                <section class="admin-panel">
                    <h2>Regla del destacado</h2>
                    <p>Solo puede existir uno. Cambiarlo no mueve ni duplica el video dentro de su colección.</p>
                </section>
            </aside>
        </div>
    </section>

    <section class="video-cms-panel" data-video-panel="add-video">
        <div class="video-cms-form-shell">
            <section class="video-cms-form-card admin-panel">
                <div class="video-cms-section-title">
                    <div><p class="admin-kicker">Nuevo contenido</p><h2>Agregar video de YouTube</h2><p>El CMS extrae automáticamente el ID y la miniatura.</p></div>
                    <span class="video-cms-status is-draft">Borrador nuevo</span>
                </div>
                @include('admin.site-editor.partials.video-form', ['video' => null, 'mode' => 'video'])
            </section>
            <aside class="video-cms-preview admin-panel" data-video-preview>
                <div><h2>Preview de tarjeta</h2><p>Así aparecerá dentro de la agrupación seleccionada.</p></div>
                <article>
                    <div class="video-cms-preview-image"><span>Agrega una URL de YouTube</span><img alt="Preview de la miniatura" data-video-preview-image hidden></div>
                    <strong data-video-preview-title>Nuevo video</strong>
                    <span data-video-preview-description>Descripción corta</span>
                    <small data-video-preview-category>Music Videos</small>
                </article>
            </aside>
        </div>
    </section>

    <section class="video-cms-panel" data-video-panel="add-playlist">
        <div class="video-cms-form-shell">
            <section class="video-cms-form-card admin-panel">
                <div class="video-cms-section-title">
                    <div><p class="admin-kicker">Nueva serie</p><h2>Agregar playlist de YouTube</h2><p>Las playlists se publican exclusivamente en Series (Playlists).</p></div>
                    <span class="video-cms-status is-draft">Borrador nuevo</span>
                </div>
                @include('admin.site-editor.partials.video-form', ['video' => null, 'mode' => 'playlist'])
            </section>
            <aside class="video-cms-preview admin-panel" data-video-preview>
                <div><h2>Preview de playlist</h2><p>Tratamiento visual especial usado por la colección Series.</p></div>
                <article>
                    <div class="video-cms-preview-image"><span>Agrega una URL de YouTube</span><img alt="Preview de playlist" data-video-preview-image hidden></div>
                    <strong data-video-preview-title>Nueva playlist</strong>
                    <span data-video-preview-description>Descripción de la serie</span>
                    <small>Series (Playlists)</small>
                </article>
            </aside>
        </div>
    </section>

    <section class="video-cms-panel" data-video-panel="banner">
        <div class="video-cms-banner-grid">
            <div class="video-cms-banner-forms">
                <section class="admin-panel video-cms-featured-editor">
                    <div class="video-cms-section-title">
                        <div><p class="admin-kicker">Hero público</p><h2>Video destacado</h2><p>La selección no altera la categoría ni el orden del video.</p></div>
                        <span class="video-cms-status is-published">Publicado</span>
                    </div>
                    <form method="POST" action="{{ route('admin.site-editor.videos.featured') }}" class="video-cms-featured-select">
                        @csrf
                        <label class="video-cms-field">
                            <span>Selecciona el destacado</span>
                            <select name="video_id" required>
                                @foreach ($videoContentForm['featureCandidates'] as $content)
                                    <option value="{{ $content->id }}" @selected($featured?->id === $content->id)>{{ $content->title }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="admin-button admin-button-primary" type="submit" @disabled(! auth()->user()->canPublishContent())>Publicar destacado</button>
                    </form>
                </section>

                @include('admin.site-editor.page-settings')
            </div>

            <section class="video-cms-live-preview admin-panel">
                <div class="video-cms-section-title">
                    <div><p class="admin-kicker">Preview público</p><h2>Hero de Videos</h2><p>Usa el website real en modo guest y no modifica producción.</p></div>
                    <a class="admin-button admin-button-ghost" href="{{ $previewUrl }}" target="_blank" rel="noreferrer">Abrir preview</a>
                </div>
                <div class="site-editor-browser-frame">
                    <iframe title="Preview público de Videos" src="{{ $previewUrl }}" loading="lazy"></iframe>
                </div>
            </section>
        </div>
    </section>
</div>
