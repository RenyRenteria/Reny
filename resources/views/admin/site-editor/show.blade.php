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

@section('content')
    <section class="admin-dashboard-section is-active site-editor-screen">
        @if ($activePage === 'music' && $musicBanner)
            @include('admin.site-editor.music-banner', [
                'banner' => $musicBanner,
                'publicUrl' => $publicUrl,
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
                                    Este bloque vive hardcodeado en la vista publica. Para editarlo hay que agregar page settings al CMS.
                                </div>
                            @endif
                        </article>
                    @endforeach
                </aside>
            </div>
        @endif
    </section>
@endsection
