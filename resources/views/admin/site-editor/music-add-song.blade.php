@php
    $canPublish = (bool) auth()->user()?->canPublishContent();
    $songMeta = fn (string $key, string $default = ''): string => (string) old("metadata.$key", $default);
@endphp

<form class="music-banner-form music-content-form" method="POST" action="{{ route('admin.content.store') }}">
    @csrf
    <input type="hidden" name="type" value="song">

    <div class="music-banner-form-grid two">
        <label>
            <span>Titulo de la cancion</span>
            <input name="title" type="text" maxlength="160" value="{{ old('title') }}" required>
        </label>
        <label>
            <span>Visibilidad</span>
            @include('admin.partials.visibility-select', [
                'name' => 'visibility',
                'selected' => old('visibility', 'open'),
                'visibilityAudiences' => $visibilityAudiences,
            ])
        </label>
    </div>

    <div class="music-banner-form-grid two">
        <label>
            <span>Slug (opcional)</span>
            <input name="slug" type="text" maxlength="180" value="{{ old('slug') }}">
        </label>
        <label>
            <span>Fecha de release</span>
            <input name="metadata[release_date]" type="date" value="{{ $songMeta('release_date') }}" required>
        </label>
    </div>

    <div class="music-banner-form-grid two">
        <label>
            <span>Duracion (segundos)</span>
            <input name="metadata[duration_seconds]" type="number" min="1" max="7200" value="{{ $songMeta('duration_seconds') }}" required>
        </label>
        <label>
            <span>Creditos</span>
            <input name="metadata[credits]" type="text" maxlength="2000" value="{{ $songMeta('credits') }}" required>
        </label>
    </div>

    <div class="music-banner-form-grid two">
        <label>
            <span>Visibilidad preview</span>
            @include('admin.partials.visibility-select', [
                'name' => 'metadata[preview_visibility]',
                'selected' => $songMeta('preview_visibility', 'open'),
                'visibilityAudiences' => $visibilityAudiences,
            ])
        </label>
        <label>
            <span>Visibilidad completa</span>
            @include('admin.partials.visibility-select', [
                'name' => 'metadata[full_visibility]',
                'selected' => $songMeta('full_visibility', 'member'),
                'visibilityAudiences' => $visibilityAudiences,
            ])
        </label>
    </div>

    <div class="music-banner-form-grid two">
        <label>
            <span>Audio</span>
            @include('admin.partials.asset-select', [
                'name' => 'metadata[audio_asset_id]',
                'selected' => $songMeta('audio_asset_id'),
                'mediaAssets' => $mediaAssets,
                'types' => ['audio'],
            ])
        </label>
        <label>
            <span>Portada</span>
            @include('admin.partials.asset-select', [
                'name' => 'metadata[cover_asset_id]',
                'selected' => $songMeta('cover_asset_id'),
                'mediaAssets' => $mediaAssets,
                'types' => ['image', 'thumbnail'],
            ])
        </label>
    </div>

    <label>
        <span>Letra (opcional)</span>
        <textarea name="metadata[lyrics]" rows="5">{{ $songMeta('lyrics') }}</textarea>
    </label>

    <div class="music-banner-actions">
        <button class="admin-button admin-button-primary" type="submit" name="action" value="publish" @disabled(! $canPublish)>Guardar y publicar</button>
        <button class="admin-button admin-button-soft" type="submit" name="action" value="draft">Guardar borrador</button>
    </div>
</form>
