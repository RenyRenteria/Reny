@php
    $canPublish = (bool) auth()->user()?->canPublishContent();
    $content = $content ?? null;
    $isEditing = $content instanceof \App\Models\EditorialContent;
    $metadata = $content?->metadata ?? [];
    $formKey = $formKey ?? ($isEditing ? 'music-song-'.$content->id : 'music-song-new');
    $oldApplies = (string) old('_music_form_key') === $formKey;
    $dateValue = fn (mixed $value): string => filled($value) ? str_replace(' ', 'T', substr((string) $value, 0, 16)) : '';
    $songTitle = $oldApplies ? (string) old('title', '') : (string) ($content?->title ?? '');
    $audioAssetId = $oldApplies ? old('metadata.audio_asset_id') : data_get($metadata, 'audio_asset_id');
    $artworkAssetId = $oldApplies ? old('metadata.artwork_asset_id') : data_get($metadata, 'artwork_asset_id');
    $memberRelease = $dateValue($oldApplies ? old('metadata.release_date_member_view') : data_get($metadata, 'release_date_member_view'));
    $openRelease = $dateValue($oldApplies ? old('metadata.release_date_open_view') : data_get($metadata, 'release_date_open_view'));
    $formAction = $isEditing ? route('admin.content.update', $content) : route('admin.content.store');
@endphp

<form class="music-banner-form music-content-form" method="POST" action="{{ $formAction }}" enctype="multipart/form-data">
    @csrf
    @if ($isEditing)
        @method('PATCH')
    @endif
    <input type="hidden" name="return_to_music_editor" value="1">
    <input type="hidden" name="_music_form_key" value="{{ $formKey }}">
    <input type="hidden" name="type" value="song">
    <input type="hidden" name="visibility" value="open">
    @if ($audioAssetId)
        <input type="hidden" name="metadata[audio_asset_id]" value="{{ $audioAssetId }}">
    @endif
    @if ($artworkAssetId)
        <input type="hidden" name="metadata[artwork_asset_id]" value="{{ $artworkAssetId }}">
    @endif

    <label>
        <span>Song name</span>
        <input name="title" type="text" maxlength="160" value="{{ $songTitle }}" required>
    </label>

    <div class="music-banner-form-grid two">
        <label>
            <span>Audio file</span>
            <input name="audio_file" type="file" accept="audio/mpeg,audio/wav,.mp3,.wav" @if (! $audioAssetId) required @endif>
            @if ($audioAssetId)
                <small>Current audio kept if no new file is uploaded.</small>
            @endif
        </label>
        <label>
            <span>Artwork</span>
            <input name="artwork" type="file" accept="image/jpeg,.jpg" @if (! $artworkAssetId) required @endif>
            @if ($artworkAssetId)
                <small>Current artwork kept if no new file is uploaded.</small>
            @endif
        </label>
    </div>

    <div class="music-banner-form-grid two">
        <label>
            <span>Member release</span>
            <input name="metadata[release_date_member_view]" type="datetime-local" value="{{ $memberRelease }}" required>
        </label>
        <label>
            <span>Open release</span>
            <input name="metadata[release_date_open_view]" type="datetime-local" value="{{ $openRelease }}" required>
        </label>
    </div>

    <div class="music-banner-actions">
        <button class="admin-button admin-button-primary" type="submit" name="action" value="publish" @disabled(! $canPublish)>Guardar y publicar</button>
        <button class="admin-button admin-button-soft" type="submit" name="action" value="draft">Guardar borrador</button>
    </div>
</form>
