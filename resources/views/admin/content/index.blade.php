@php
    $contentCards = $contents->map(function ($content) use ($timezone) {
        $status = $content->status->value;
        $visibility = $content->visibility->value;

        return [
            'id' => $content->id,
            'title' => $content->title,
            'summary' => $content->summary,
            'type' => str_replace('_', ' ', $content->type->value),
            'status' => $status,
            'visibility' => $visibility,
            'needsApproval' => $content->needs_approval,
            'filter' => $status === 'draft'
                ? 'borrador'
                : (in_array($visibility, ['royal', 'member', 'purchased'], true) ? 'royal' : 'publico'),
            'editUrl' => route('admin.content.edit', $content),
            'previewUrl' => route('admin.content.preview', $content),
            'timestamp' => $content->scheduled_at
                ? 'Programado para '.$content->scheduled_at->copy()->timezone($timezone)->format('j M Y, g:i A')
                : $content->created_at->format('j M Y, g:i A'),
        ];
    });
@endphp

@extends('admin.layout')

@section('title', 'Contenido CMS')
@section('admin_section', 'contenido')
@section('admin_theme', 'music')

@section('content')
    <section class="admin-dashboard-section is-active">
        <div class="admin-page-heading">
            <div>
                <h1>Contenido de tu Sitio</h1>
                <p>Aqui organizas toda la musica, videos, fotos y noticias que tus fans pueden ver.</p>
            </div>
            <a class="admin-button admin-button-primary" href="{{ route('admin.content.create') }}">+ Subir o escribir algo nuevo</a>
        </div>

        <section class="admin-panel" aria-labelledby="create-title">
            <div class="admin-section-head">
                <div>
                    <p class="admin-kicker">Tipos de contenido</p>
                    <h2 id="create-title">Crear contenido</h2>
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
                <div class="admin-status-filter-row">
                    <a @class(['is-active' => $activeStatus === null]) href="{{ route('admin.content.index', array_filter(['section' => $activeSection])) }}">All</a>
                    @foreach ($statuses as $status)
                        <a @class(['is-active' => $activeStatus === $status->value]) href="{{ route('admin.content.index', array_filter(['section' => $activeSection, 'status' => $status->value])) }}">
                            {{ $status->value }}
                        </a>
                    @endforeach
                </div>
            </div>

            @include('admin.partials.content-cards', ['contents' => $contentCards])
        </section>
    </section>
@endsection
