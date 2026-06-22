@php
    $canPublish = (bool) auth()->user()?->canPublishContent();
    $albumMeta = fn (string $key, string $default = ''): string => (string) old("metadata.$key", $default);
    $tracks = collect(old('metadata.tracks', [['track_name' => '', 'release_date_member_view' => '']]))->values();
@endphp

<form class="music-banner-form music-content-form" method="POST" action="{{ route('admin.content.store') }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="type" value="musical_album">
    <input type="hidden" name="visibility" value="open">

    <label>
        <span>Album name</span>
        <input name="title" type="text" maxlength="160" value="{{ old('title') }}" required>
    </label>

    <div class="music-banner-form-grid two">
        <label>
            <span>Album artwork</span>
            <input name="album_artwork" type="file" accept="image/jpeg,.jpg" required>
        </label>
        <label>
            <span>Member release</span>
            <input name="metadata[release_date_member_view]" type="datetime-local" value="{{ $albumMeta('release_date_member_view') }}" required>
        </label>
    </div>

    <label>
        <span>Open release</span>
        <input name="metadata[release_date_open_view]" type="datetime-local" value="{{ $albumMeta('release_date_open_view') }}" required>
    </label>

    <div class="music-track-builder" data-album-tracks>
        <div class="music-track-builder-head">
            <span>Tracks</span>
            <button class="admin-button admin-button-soft" type="button" data-add-track>Agregar track</button>
        </div>

        <div data-track-list>
            @foreach ($tracks as $index => $track)
                <div class="music-track-row" data-track-row>
                    <div class="music-banner-form-grid two">
                        <label>
                            <span>Track name</span>
                            <input name="metadata[tracks][{{ $index }}][track_name]" type="text" maxlength="160" value="{{ $track['track_name'] ?? '' }}" required>
                        </label>
                        <label>
                            <span>Track audio file</span>
                            <input name="track_audio_files[{{ $index }}]" type="file" accept="audio/mpeg,audio/wav,.mp3,.wav" required>
                        </label>
                    </div>
                    <div class="music-banner-form-grid two">
                        <label>
                            <span>Track member release override</span>
                            <input name="metadata[tracks][{{ $index }}][release_date_member_view]" type="datetime-local" value="{{ $track['release_date_member_view'] ?? '' }}">
                        </label>
                        <button class="admin-button admin-button-ghost" type="button" data-remove-track @disabled($tracks->count() === 1)>Eliminar track</button>
                    </div>
                </div>
            @endforeach
        </div>

        <template data-track-template>
            <div class="music-track-row" data-track-row>
                <div class="music-banner-form-grid two">
                    <label>
                        <span>Track name</span>
                        <input data-track-name type="text" maxlength="160" required>
                    </label>
                    <label>
                        <span>Track audio file</span>
                        <input data-track-audio type="file" accept="audio/mpeg,audio/wav,.mp3,.wav" required>
                    </label>
                </div>
                <div class="music-banner-form-grid two">
                    <label>
                        <span>Track member release override</span>
                        <input data-track-release type="datetime-local">
                    </label>
                    <button class="admin-button admin-button-ghost" type="button" data-remove-track>Eliminar track</button>
                </div>
            </div>
        </template>
    </div>

    <div class="music-banner-actions">
        <button class="admin-button admin-button-primary" type="submit" name="action" value="publish" @disabled(! $canPublish)>Guardar y publicar</button>
        <button class="admin-button admin-button-soft" type="submit" name="action" value="draft">Guardar borrador</button>
    </div>
</form>

<script>
    (() => {
        const root = document.querySelector('[data-album-tracks]');
        if (!root) return;

        const list = root.querySelector('[data-track-list]');
        const template = root.querySelector('[data-track-template]');

        const syncRows = () => {
            const rows = Array.from(list.querySelectorAll('[data-track-row]'));
            rows.forEach((row, index) => {
                row.querySelector('[name$="[track_name]"], [data-track-name]').name = `metadata[tracks][${index}][track_name]`;
                row.querySelector('[name^="track_audio_files"], [data-track-audio]').name = `track_audio_files[${index}]`;
                row.querySelector('[name$="[release_date_member_view]"], [data-track-release]').name = `metadata[tracks][${index}][release_date_member_view]`;
                const asset = row.querySelector('[name$="[track_audio_asset_id]"]');
                if (asset) asset.name = `metadata[tracks][${index}][track_audio_asset_id]`;
                const remove = row.querySelector('[data-remove-track]');
                if (remove) remove.disabled = rows.length === 1;
            });
        };

        root.querySelector('[data-add-track]')?.addEventListener('click', () => {
            list.append(template.content.cloneNode(true));
            syncRows();
        });

        root.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-track]');
            if (!button) return;

            button.closest('[data-track-row]')?.remove();
            syncRows();
        });

        syncRows();
    })();
</script>
