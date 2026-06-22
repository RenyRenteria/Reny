@php
    $canPublish = (bool) auth()->user()?->canPublishContent();
    $selectedTracks = collect(old('metadata.tracks', []))->map(fn ($track): string => (string) $track)->all();
@endphp

<form class="music-banner-form music-content-form" method="POST" action="{{ route('admin.content.store') }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="type" value="music_playlist">
    <input type="hidden" name="visibility" value="open">

    <label>
        <span>Playlist name</span>
        <input name="title" type="text" maxlength="160" value="{{ old('title') }}" required>
    </label>

    <div class="music-banner-form-grid two">
        <label>
            <span>Cover image</span>
            <input name="playlist_cover" type="file" accept="image/jpeg,.jpg" required>
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
