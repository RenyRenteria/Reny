@php
    $payloadSource = $publicPayload['_cms_source'] ?? 'cms';
    $payloadFallback = (bool) ($publicPayload['_cms_fallback'] ?? false);
    $payloadSections = collect($publicPayload)
        ->reject(fn ($value, string $key): bool => str_starts_with($key, '_'))
        ->map(function ($value, string $key): array {
            $count = is_countable($value) ? count($value) : ($value ? 1 : 0);

            return [
                'label' => str($key)->headline()->toString(),
                'count' => $count,
            ];
        })
        ->values();
@endphp

@extends('admin.layout')

@section('title', 'Reny Site Editor')
@section('admin_section', 'site-editor')
@section('admin_theme', $pageConfig['theme'])

@php
    $musicInitialTab = 'banner';

    if ($errors->any()) {
        $erroredType = old('type');
        $musicFormKey = (string) old('_music_form_key', '');

        if ($musicFormKey !== '' && ! str_ends_with($musicFormKey, '-new')) {
            $musicInitialTab = 'manage';
        } elseif ($erroredType === 'song') {
            $musicInitialTab = 'song';
        } elseif (in_array($erroredType, ['musical_album', 'deluxe_album'], true)) {
            $musicInitialTab = 'album';
        } elseif ($erroredType === 'music_playlist') {
            $musicInitialTab = 'playlist';
        }
    }
@endphp

@section('content')
    <section class="admin-dashboard-section is-active site-editor-screen">
        @if ($pageSettings && $pageSettingsForm)
            @include('admin.site-editor.page-settings')
        @endif

        <nav class="admin-actions" aria-label="Audience previews">
            @foreach (\App\Enums\VisibilityAudience::cases() as $audience)
                <a class="admin-button admin-button-ghost" href="{{ route('admin.site-editor.preview', ['page' => $activePage, 'audience' => $audience->value]) }}" target="_blank" rel="noreferrer">
                    {{ $audience === \App\Enums\VisibilityAudience::Open ? 'Guest' : str($audience->value)->headline() }} preview
                </a>
            @endforeach
        </nav>

        @if (in_array($activePage, ['home', 'store'], true) && $storefront)
            @include('admin.site-editor.storefront', [
                'storefront' => $storefront,
                'mediaAssets' => $storefrontForm['mediaAssets'],
                'storeContents' => $storefrontForm['storeContents'],
                'publicUrl' => $publicUrl,
                'editorPage' => $activePage,
            ])
        @elseif ($activePage === 'music' && $musicBanner)
            <div class="music-cms-screens" data-music-cms data-music-initial-tab="{{ $musicInitialTab }}">
                <nav class="music-cms-tabs" role="tablist" aria-label="Editor de la pestana de musica">
                    <button type="button" class="music-cms-tab" role="tab" data-music-tab="banner">Edit Banner</button>
                    <button type="button" class="music-cms-tab" role="tab" data-music-tab="album">Add Album</button>
                    <button type="button" class="music-cms-tab" role="tab" data-music-tab="song">Add Song</button>
                    <button type="button" class="music-cms-tab" role="tab" data-music-tab="playlist">Add Playlist</button>
                    <button type="button" class="music-cms-tab" role="tab" data-music-tab="manage">Manage Music</button>
                </nav>

                <div class="music-cms-panel" data-music-panel="banner">
                    @include('admin.site-editor.music-banner', [
                        'banner' => $musicBanner,
                        'publicUrl' => $publicUrl,
                    ])
                </div>

                <div class="music-cms-panel" data-music-panel="album">
                    <div class="music-banner-cms">
                        <div class="admin-page-heading music-banner-heading">
                            <div>
                                <h1>Add Album</h1>
                                <p>Music / agrega un album al website publico.</p>
                            </div>
                            <a class="admin-button admin-button-ghost" href="{{ $publicUrl }}" target="_blank" rel="noreferrer">Ver website</a>
                        </div>
                        <section class="music-banner-editor music-content-editor admin-panel" aria-label="Formulario para agregar album">
                            <div class="music-banner-panel-head">
                                <div>
                                    <p class="admin-kicker">Nuevo album</p>
                                    <h2>Campos del album</h2>
                                </div>
                            </div>
                            @include('admin.site-editor.music-add-album', [
                                'visibilityAudiences' => $musicContentForm['visibilityAudiences'],
                                'mediaAssets' => $musicContentForm['mediaAssets'],
                                'formKey' => 'music-album-new',
                            ])
                        </section>
                    </div>
                </div>

                <div class="music-cms-panel" data-music-panel="song">
                    <div class="music-banner-cms">
                        <div class="admin-page-heading music-banner-heading">
                            <div>
                                <h1>Add Song</h1>
                                <p>Music / agrega una cancion al website publico.</p>
                            </div>
                            <a class="admin-button admin-button-ghost" href="{{ $publicUrl }}" target="_blank" rel="noreferrer">Ver website</a>
                        </div>
                        <section class="music-banner-editor music-content-editor admin-panel" aria-label="Formulario para agregar cancion">
                            <div class="music-banner-panel-head">
                                <div>
                                    <p class="admin-kicker">Nueva cancion</p>
                                    <h2>Campos de la cancion</h2>
                                </div>
                            </div>
                            @include('admin.site-editor.music-add-song', [
                                'visibilityAudiences' => $musicContentForm['visibilityAudiences'],
                                'mediaAssets' => $musicContentForm['mediaAssets'],
                                'formKey' => 'music-song-new',
                            ])
                        </section>
                    </div>
                </div>

                <div class="music-cms-panel" data-music-panel="playlist">
                    <div class="music-banner-cms">
                        <div class="admin-page-heading music-banner-heading">
                            <div>
                                <h1>Add Playlist</h1>
                                <p>Music / agrega una playlist al website publico.</p>
                            </div>
                            <a class="admin-button admin-button-ghost" href="{{ $publicUrl }}" target="_blank" rel="noreferrer">Ver website</a>
                        </div>
                        <section class="music-banner-editor music-content-editor admin-panel" aria-label="Formulario para agregar playlist">
                            <div class="music-banner-panel-head">
                                <div>
                                    <p class="admin-kicker">Nueva playlist</p>
                                    <h2>Campos de la playlist</h2>
                                </div>
                            </div>
                            @include('admin.site-editor.music-add-playlist', [
                                'visibilityAudiences' => $musicContentForm['visibilityAudiences'],
                                'mediaAssets' => $musicContentForm['mediaAssets'],
                                'trackOptions' => $musicContentForm['trackOptions'],
                                'formKey' => 'music-playlist-new',
                            ])
                        </section>
                    </div>
                </div>

                <div class="music-cms-panel" data-music-panel="manage">
                    <div class="music-banner-cms">
                        <div class="admin-page-heading music-banner-heading">
                            <div>
                                <h1>Manage Music</h1>
                                <p>Music / edita o elimina canciones, albumes y playlists.</p>
                            </div>
                            <a class="admin-button admin-button-ghost" href="{{ $publicUrl }}" target="_blank" rel="noreferrer">Ver website</a>
                        </div>

                        @include('admin.site-editor.music-manage', [
                            'contents' => $musicContentForm['contents'],
                            'visibilityAudiences' => $musicContentForm['visibilityAudiences'],
                            'mediaAssets' => $musicContentForm['mediaAssets'],
                            'trackOptions' => $musicContentForm['trackOptions'],
                        ])
                    </div>
                </div>
            </div>

            <script>
                (() => {
                    const root = document.querySelector('[data-music-cms]');

                    if (!root) return;

                    const tabs = Array.from(root.querySelectorAll('[data-music-tab]'));
                    const panels = Array.from(root.querySelectorAll('[data-music-panel]'));

                    const activate = (name) => {
                        tabs.forEach((tab) => {
                            const isActive = tab.dataset.musicTab === name;
                            tab.classList.toggle('is-active', isActive);
                            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        });
                        panels.forEach((panel) => {
                            panel.classList.toggle('is-active', panel.dataset.musicPanel === name);
                        });
                    };

                    tabs.forEach((tab) => {
                        tab.addEventListener('click', () => activate(tab.dataset.musicTab));
                    });

                    activate(root.dataset.musicInitialTab || 'banner');
                })();
            </script>
        @elseif ($activePage === 'community' && $communityPostForm)
            @include('admin.site-editor.community-posts', [
                'communityPostForm' => $communityPostForm,
                'communityRsvps' => $communityRsvps,
                'pages' => $pages,
                'activePage' => $activePage,
                'publicUrl' => $publicUrl,
                'previewUrl' => $previewUrl,
            ])
        @else
            <div class="admin-page-heading">
                <div>
                    <h1>Reny Site Editor</h1>
                    <p>Edita el CMS desde una vista page-first que usa el website publico como preview real.</p>
                </div>
                <div class="admin-actions">
                    <a class="admin-button admin-button-ghost" href="{{ $publicUrl }}" target="_blank" rel="noreferrer">Abrir website</a>
                    <a class="admin-button admin-button-soft" href="{{ $previewUrl }}" target="_blank" rel="noreferrer">Abrir preview guest</a>
                    <a class="admin-button admin-button-primary" href="{{ route('admin.content.create') }}">Nuevo bloque</a>
                </div>
            </div>

            <nav class="site-editor-page-tabs" aria-label="Site editor pages">
                @foreach ($pages as $key => $page)
                    <a @class(['is-active' => $key === $activePage]) href="{{ route('admin.site-editor.show', ['page' => $key]) }}">
                        <span>{{ $page['label'] }}</span>
                        <small>{{ $page['public_path'] }}</small>
                    </a>
                @endforeach
            </nav>

            <div class="site-editor-workspace">
                <section class="site-editor-preview-panel" aria-labelledby="site-editor-preview-title">
                    <div class="site-editor-preview-toolbar">
                        <div>
                            <p class="admin-kicker">Preview publico</p>
                            <h2 id="site-editor-preview-title">{{ $pageConfig['label'] }}</h2>
                            <span>{{ $pageConfig['summary'] }} Se renderiza como guest, sin permisos de admin.</span>
                        </div>
                        <div class="admin-badges">
                            <span class="admin-status-pill admin-status-info">guest</span>
                            <span @class([
                                'admin-status-pill',
                                'admin-status-success' => ! $payloadFallback,
                                'admin-status-warning' => $payloadFallback,
                            ])>
                                {{ $payloadFallback ? 'Fallback activo' : 'CMS conectado' }}
                            </span>
                            <span class="admin-status-pill admin-status-info">{{ $payloadSource }}</span>
                        </div>
                    </div>

                    <div class="site-editor-browser-frame">
                        <iframe
                            title="Preview publico de {{ $pageConfig['label'] }}"
                            src="{{ $previewUrl }}"
                            data-preview-audience="guest"
                            loading="lazy"
                        ></iframe>
                    </div>
                </section>

                <aside class="site-editor-block-panel" aria-label="Bloques editables">
                    <section class="admin-panel site-editor-payload-card">
                        <div class="admin-section-head">
                            <div>
                                <p class="admin-kicker">Payload publico</p>
                                <h2>Secciones CMS</h2>
                            </div>
                        </div>

                        <div class="site-editor-payload-grid">
                            @forelse ($payloadSections as $section)
                                <div>
                                    <strong>{{ $section['count'] }}</strong>
                                    <span>{{ $section['label'] }}</span>
                                </div>
                            @empty
                                <div>
                                    <strong>0</strong>
                                    <span>Sin payload</span>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    @foreach ($blocks as $block)
                        <article class="admin-panel site-editor-block-card" data-editor-block="{{ $block['key'] }}">
                            <div class="site-editor-block-head">
                                <div>
                                    <p class="admin-kicker">Bloque</p>
                                    <h2>{{ $block['label'] }}</h2>
                                    <span>{{ $block['note'] }}</span>
                                </div>
                                <span @class([
                                    'admin-status-pill',
                                    'admin-status-success' => $block['status_tone'] === 'success',
                                    'admin-status-info' => $block['status_tone'] === 'info',
                                    'admin-status-warning' => $block['status_tone'] === 'warning',
                                ])>
                                    {{ $block['status_label'] }}
                                </span>
                            </div>

                            @if ($block['persistable'])
                                <div class="site-editor-count-row">
                                    <span>{{ (int) ($block['counts']->get('published') ?? 0) }} publicados</span>
                                    <span>{{ (int) ($block['counts']->get('draft') ?? 0) }} borradores</span>
                                    <span>{{ (int) ($block['counts']->get('scheduled') ?? 0) }} programados</span>
                                </div>

                                <div class="site-editor-content-list">
                                    @forelse ($block['contents'] as $content)
                                        <div class="site-editor-content-row">
                                            <div>
                                                <strong>{{ $content->title }}</strong>
                                                <span>{{ str_replace('_', ' ', $content->type->value) }} · {{ $content->status->value }}</span>
                                            </div>
                                            <div class="admin-actions">
                                                <a class="admin-button admin-button-ghost" href="{{ route('admin.content.preview', $content) }}">Preview</a>
                                                <a class="admin-button admin-button-soft" href="{{ route('admin.content.edit', $content) }}">Editar</a>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="admin-empty-state">Este bloque todavia no tiene contenido CMS.</div>
                                    @endforelse
                                </div>

                                <div class="admin-form-actions">
                                    <a class="admin-button admin-button-primary" href="{{ $block['create_url'] }}">Agregar {{ str_replace('_', ' ', $block['default_type']) }}</a>
                                </div>
                            @else
                                <div class="admin-empty-state">
                                    Este bloque se edita en Page settings y alimenta el mismo payload que la pagina publica.
                                </div>
                            @endif
                        </article>
                    @endforeach
                </aside>
            </div>
        @endif
    </section>
@endsection
