@php
    $video = $video ?? null;
    $isEditing = $video?->exists ?? false;
    $isPlaylist = ($mode ?? 'video') === 'playlist';
    $metadata = $video?->metadata ?? [];
    $field = fn (string $key, mixed $default = '') => old("metadata.{$key}", data_get($metadata, $key, $default));
    $visibility = old('visibility', $video?->visibility->value ?? 'open');
    $category = $isPlaylist ? 'series' : old('metadata.category', \App\Support\VideoCatalog::groupFor($video));
    $scheduledAt = old('scheduled_at', $video?->scheduled_at?->copy()->timezone($timezone)->format('Y-m-d\TH:i'));
@endphp

<form
    class="video-cms-form"
    method="POST"
    action="{{ $isEditing ? route('admin.content.update', $video) : route('admin.content.store') }}"
    data-video-content-form
    data-video-content-kind="{{ $isPlaylist ? 'playlist' : 'video' }}"
>
    @csrf
    @if ($isEditing)
        @method('PATCH')
    @endif
    <input type="hidden" name="return_to_video_editor" value="1">
    <input type="hidden" name="_video_editor_tab" value="{{ $isEditing ? 'catalog' : ($isPlaylist ? 'add-playlist' : 'add-video') }}">
    <input type="hidden" name="type" value="video">
    <input type="hidden" name="metadata[access_tier]" value="{{ $visibility }}" data-video-access-tier>
    @if ($isPlaylist)
        <input type="hidden" name="metadata[category]" value="series">
    @endif

    <div class="video-cms-form-grid">
        <label class="video-cms-field video-cms-field-wide">
            <span>{{ $isPlaylist ? 'URL de playlist o primer episodio' : 'URL de YouTube' }} *</span>
            <input name="metadata[youtube_url]" type="url" maxlength="500" required value="{{ $field('youtube_url') }}" data-video-url>
            <small>Acepta youtube.com/watch, youtu.be, shorts y embed.</small>
        </label>
        <label class="video-cms-field">
            <span>{{ $isPlaylist ? 'Nombre de la serie' : 'Título público' }} *</span>
            <input name="title" maxlength="160" required value="{{ old('title', $video?->title) }}" data-video-title>
        </label>
        @if ($isPlaylist)
            <label class="video-cms-field">
                <span>Agrupación</span>
                <input value="Series (Playlists)" disabled>
            </label>
        @else
            <label class="video-cms-field">
                <span>Agrupación *</span>
                <select name="metadata[category]" required data-video-category>
                    @foreach ($videoContentForm['groups'] as $groupKey => $group)
                        <option value="{{ $groupKey }}" @selected($category === $groupKey)>{{ $group['label'] }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="video-cms-field video-cms-field-wide">
            <span>Descripción corta</span>
            <textarea name="summary" maxlength="500" rows="3" data-video-description>{{ old('summary', $video?->summary) }}</textarea>
            <small>Se muestra debajo del título en la tarjeta pública.</small>
        </label>
        <label class="video-cms-field">
            <span>Audiencia</span>
            <select name="visibility" data-video-visibility>
                @foreach ($videoContentForm['visibilityAudiences'] as $audience)
                    <option value="{{ $audience->value }}" @selected($visibility === $audience->value)>{{ str($audience->value)->headline() }}</option>
                @endforeach
            </select>
        </label>
        <label class="video-cms-field">
            <span>Publicar o programar para</span>
            <input name="scheduled_at" type="datetime-local" value="{{ $scheduledAt }}">
        </label>
        @if ($isPlaylist)
            <input name="metadata[is_featured]" type="hidden" value="0">
        @else
            <label class="video-cms-check">
                <input name="metadata[is_featured]" type="hidden" value="0">
                <input name="metadata[is_featured]" type="checkbox" value="1" @checked((bool) $field('is_featured', false))>
                <span>Usar como video destacado sin moverlo de su colección</span>
            </label>
        @endif
    </div>

    <div class="video-cms-form-actions">
        <button class="admin-button admin-button-soft" name="action" value="draft" type="submit">Guardar borrador</button>
        <button class="admin-button admin-button-warning" name="action" value="schedule" type="submit" @disabled(! auth()->user()->canPublishContent())>Programar</button>
        <button class="admin-button admin-button-primary" name="action" value="publish" type="submit" @disabled(! auth()->user()->canPublishContent())>{{ $isEditing ? 'Guardar y publicar' : ($isPlaylist ? 'Publicar playlist' : 'Publicar video') }}</button>
    </div>
</form>
