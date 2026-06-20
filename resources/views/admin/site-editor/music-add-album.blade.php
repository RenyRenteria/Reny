@php
    $canPublish = (bool) auth()->user()?->canPublishContent();
    $albumMeta = fn (string $key, string $default = ''): string => (string) old("metadata.$key", $default);
    $albumType = old('type', 'musical_album');
    if (! in_array($albumType, ['musical_album', 'deluxe_album'], true)) {
        $albumType = 'musical_album';
    }
@endphp

<form class="music-banner-form music-content-form" method="POST" action="{{ route('admin.content.store') }}">
    @csrf

    <div class="music-banner-form-grid two">
        <label>
            <span>Tipo de album</span>
            <select name="type">
                <option value="musical_album" @selected($albumType === 'musical_album')>Album musical</option>
                <option value="deluxe_album" @selected($albumType === 'deluxe_album')>Album deluxe</option>
            </select>
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

    <label>
        <span>Titulo del album</span>
        <input name="title" type="text" maxlength="160" value="{{ old('title') }}" required>
    </label>

    <div class="music-banner-form-grid two">
        <label>
            <span>Slug (opcional)</span>
            <input name="slug" type="text" maxlength="180" value="{{ old('slug') }}">
        </label>
        <label>
            <span>Ciclo de release</span>
            <input name="metadata[release_cycle]" type="text" maxlength="160" value="{{ $albumMeta('release_cycle') }}" required>
        </label>
    </div>

    <div class="music-banner-form-grid two">
        <label>
            <span>Precio (centavos, opcional)</span>
            <input name="metadata[price_cents]" type="number" min="0" value="{{ $albumMeta('price_cents') }}">
        </label>
        <label>
            <span>Portada</span>
            @include('admin.partials.asset-select', [
                'name' => 'metadata[cover_asset_id]',
                'selected' => $albumMeta('cover_asset_id'),
                'mediaAssets' => $mediaAssets,
                'types' => ['image', 'thumbnail'],
            ])
        </label>
    </div>

    <label>
        <span>Resumen (opcional)</span>
        <textarea name="summary" maxlength="500" rows="2">{{ old('summary') }}</textarea>
    </label>

    <label>
        <span>Tracklist</span>
        <textarea name="metadata[tracklist]" rows="5" required>{{ $albumMeta('tracklist') }}</textarea>
    </label>

    <label>
        <span>Narrativa</span>
        <textarea name="metadata[narrative]" rows="5" required>{{ $albumMeta('narrative') }}</textarea>
    </label>

    <div class="music-banner-actions">
        <button class="admin-button admin-button-primary" type="submit" name="action" value="publish" @disabled(! $canPublish)>Guardar y publicar</button>
        <button class="admin-button admin-button-soft" type="submit" name="action" value="draft">Guardar borrador</button>
    </div>
</form>
