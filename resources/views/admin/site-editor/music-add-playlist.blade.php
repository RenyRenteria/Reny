@php
    $canPublish = (bool) auth()->user()?->canPublishContent();
    $content = $content ?? null;
    $isEditing = $content instanceof \App\Models\EditorialContent;
    $metadata = $content?->metadata ?? [];
    $formKey = $formKey ?? ($isEditing ? 'music-playlist-'.$content->id : 'music-playlist-new');
    $oldApplies = (string) old('_music_form_key') === $formKey;
    $playlistTitle = $oldApplies ? (string) old('title', '') : (string) ($content?->title ?? '');
    $coverAssetId = $oldApplies ? old('metadata.playlist_cover_asset_id') : data_get($metadata, 'playlist_cover_asset_id');
    $selectedTracks = collect($oldApplies ? old('metadata.tracks', []) : data_get($metadata, 'tracks', []))->map(fn ($track): string => (string) $track)->all();
    $formAction = $isEditing ? route('admin.content.update', $content) : route('admin.content.store');
@endphp

<form class="music-banner-form music-content-form" method="POST" action="{{ $formAction }}" enctype="multipart/form-data">
    @csrf
    @if ($isEditing)
        @method('PATCH')
    @endif
    <input type="hidden" name="return_to_music_editor" value="1">
    <input type="hidden" name="_music_form_key" value="{{ $formKey }}">
    <input type="hidden" name="type" value="music_playlist">
    <input type="hidden" name="visibility" value="open">
    @if ($coverAssetId)
        <input type="hidden" name="metadata[playlist_cover_asset_id]" value="{{ $coverAssetId }}">
    @endif

    <label>
        <span>Playlist name</span>
        <input name="title" type="text" maxlength="160" value="{{ $playlistTitle }}" required>
    </label>

    <div class="music-banner-form-grid two">
        <label>
            <span>Cover image</span>
            <input name="playlist_cover" type="file" accept="image/jpeg,.jpg" @if (! $coverAssetId) required @endif>
            @if ($coverAssetId)
                <small>Current cover kept if no new file is uploaded.</small>
            @endif
        </label>
        <label>
            <span>Tracks</span>
            <select name="metadata[tracks][]" multiple size="8" required>
                @forelse ($trackOptions as $track)
                    <option value="{{ $track['value'] }}" @selected(in_array($track['value'], $selectedTracks, true))>
                        {{ $track['group'] }} - {{ $track['label'] }}
                    </option>
                @empty
                    <option value="" disabled>No hay canciones o tracks disponibles todavia.</option>
                @endforelse
            </select>
        </label>
    </div>

    <div class="music-banner-actions">
        <button class="admin-button admin-button-primary" type="submit" name="action" value="publish" @disabled(! $canPublish || count($trackOptions) === 0)>Guardar y publicar</button>
        <button class="admin-button admin-button-soft" type="submit" name="action" value="draft" @disabled(count($trackOptions) === 0)>Guardar borrador</button>
    </div>
</form>
