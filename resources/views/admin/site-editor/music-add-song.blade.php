@php
    $canPublish = (bool) auth()->user()?->canPublishContent();
    $songMeta = fn (string $key, string $default = ''): string => (string) old("metadata.$key", $default);
@endphp

<form class="music-banner-form music-content-form" method="POST" action="{{ route('admin.content.store') }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="type" value="song">
    <input type="hidden" name="visibility" value="open">

    <label>
        <span>Song name</span>
        <input name="title" type="text" maxlength="160" value="{{ old('title') }}" required>
    </label>

    <div class="music-banner-form-grid two">
        <label>
            <span>Audio file</span>
            <input name="audio_file" type="file" accept="audio/mpeg,audio/wav,.mp3,.wav" required>
        </label>
        <label>
            <span>Artwork</span>
            <input name="artwork" type="file" accept="image/jpeg,.jpg" required>
        </label>
    </div>

    <div class="music-banner-form-grid two">
        <label>
            <span>Member release</span>
            <input name="metadata[release_date_member_view]" type="datetime-local" value="{{ $songMeta('release_date_member_view') }}" required>
        </label>
        <label>
            <span>Open release</span>
            <input name="metadata[release_date_open_view]" type="datetime-local" value="{{ $songMeta('release_date_open_view') }}" required>
        </label>
    </div>

    <div class="music-banner-actions">
        <button class="admin-button admin-button-primary" type="submit" name="action" value="publish" @disabled(! $canPublish)>Guardar y publicar</button>
        <button class="admin-button admin-button-soft" type="submit" name="action" value="draft">Guardar borrador</button>
    </div>
</form>
