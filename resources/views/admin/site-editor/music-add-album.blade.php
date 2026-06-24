@php
    $canPublish = (bool) auth()->user()?->canPublishContent();
    $content = $content ?? null;
    $isEditing = $content instanceof \App\Models\EditorialContent;
    $metadata = $content?->metadata ?? [];
    $formKey = $formKey ?? ($isEditing ? 'music-album-'.$content->id : 'music-album-new');
    $oldApplies = (string) old('_music_form_key') === $formKey;
    $dateValue = fn (mixed $value): string => filled($value) ? str_replace(' ', 'T', substr((string) $value, 0, 16)) : '';
    $albumTitle = $oldApplies ? (string) old('title', '') : (string) ($content?->title ?? '');
    $artworkAssetId = $oldApplies ? old('metadata.album_artwork_asset_id') : data_get($metadata, 'album_artwork_asset_id');
    $memberRelease = $dateValue($oldApplies ? old('metadata.release_date_member_view') : data_get($metadata, 'release_date_member_view'));
    $openRelease = $dateValue($oldApplies ? old('metadata.release_date_open_view') : data_get($metadata, 'release_date_open_view'));
    $tracks = collect($oldApplies ? old('metadata.tracks', []) : data_get($metadata, 'tracks', []))->values();
    $tracks = $tracks->isEmpty() ? collect([['track_name' => '', 'release_date_member_view' => '']]) : $tracks;
    $formAction = $isEditing ? route('admin.content.update', $content) : route('admin.content.store');
@endphp

<form
    class="music-banner-form music-content-form"
    method="POST"
    action="{{ $formAction }}"
    enctype="multipart/form-data"
    data-album-upload-progress-form
    data-upload-success-url="{{ route('admin.site-editor.show', ['page' => 'music']) }}"
>
    @csrf
    @if ($isEditing)
        @method('PATCH')
    @endif
    <input type="hidden" name="return_to_music_editor" value="1">
    <input type="hidden" name="_music_form_key" value="{{ $formKey }}">
    <input type="hidden" name="type" value="musical_album">
    <input type="hidden" name="visibility" value="open">
    @if ($artworkAssetId)
        <input type="hidden" name="metadata[album_artwork_asset_id]" value="{{ $artworkAssetId }}">
    @endif

    <label>
        <span>Album name</span>
        <input name="title" type="text" maxlength="160" value="{{ $albumTitle }}" required>
    </label>

    <div class="music-banner-form-grid two">
        <label>
            <span>Album artwork</span>
            <input name="album_artwork" type="file" accept="image/jpeg,.jpg" @if (! $artworkAssetId) required @endif>
            @if ($artworkAssetId)
                <small>Current artwork kept if no new file is uploaded.</small>
            @endif
        </label>
        <label>
            <span>Member release</span>
            <input name="metadata[release_date_member_view]" type="datetime-local" value="{{ $memberRelease }}" required>
        </label>
    </div>

    <label>
        <span>Open release</span>
        <input name="metadata[release_date_open_view]" type="datetime-local" value="{{ $openRelease }}" required>
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
                            @if ($track['track_audio_asset_id'] ?? null)
                                <input type="hidden" name="metadata[tracks][{{ $index }}][track_audio_asset_id]" value="{{ $track['track_audio_asset_id'] }}">
                            @endif
                            <input name="track_audio_files[{{ $index }}]" type="file" accept="audio/mpeg,audio/wav,.mp3,.wav" @if (! ($track['track_audio_asset_id'] ?? null)) required @endif>
                            @if ($track['track_audio_asset_id'] ?? null)
                                <small>Current audio kept if no new file is uploaded.</small>
                            @endif
                        </label>
                    </div>
                    <div class="music-banner-form-grid two">
                        <label>
                            <span>Track member release override</span>
                            <input name="metadata[tracks][{{ $index }}][release_date_member_view]" type="datetime-local" value="{{ $dateValue($track['release_date_member_view'] ?? '') }}">
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

    @include('admin.site-editor.partials.upload-progress', [
        'label' => 'Estado del upload del album',
    ])
</form>

<script>
    (() => {
        document.querySelectorAll('[data-album-tracks]:not([data-album-tracks-ready])').forEach((root) => {
            root.dataset.albumTracksReady = 'true';
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
        });
    })();
</script>

@once
    <script>
        (() => {
            const readyAttribute = 'data-album-upload-progress-ready';

            const formatBytes = (bytes) => {
                if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB';

                const units = ['B', 'KB', 'MB', 'GB'];
                let size = bytes;
                let unitIndex = 0;

                while (size >= 1024 && unitIndex < units.length - 1) {
                    size /= 1024;
                    unitIndex += 1;
                }

                return `${size >= 10 || unitIndex === 0 ? Math.round(size) : size.toFixed(1)} ${units[unitIndex]}`;
            };

            const clampPercent = (value) => Math.max(0, Math.min(100, Math.round(value)));

            const parseJson = (text) => {
                try {
                    return JSON.parse(text);
                } catch (error) {
                    return {};
                }
            };

            const responseMessage = (xhr, fallback) => {
                const payload = parseJson(xhr.responseText || '{}');
                const errors = payload.errors && typeof payload.errors === 'object'
                    ? Object.values(payload.errors).flat().filter(Boolean)
                    : [];

                if (errors.length > 0) {
                    return errors.slice(0, 3).join(' ');
                }

                return payload.message || fallback;
            };

            const uploadItemsFor = (form) => {
                const inputs = Array.from(form.querySelectorAll('input[type="file"]'));
                const items = [];

                inputs.forEach((input) => {
                    Array.from(input.files || []).forEach((file) => {
                        const row = input.closest('[data-track-row]');
                        const trackName = row?.querySelector('[name$="[track_name]"]')?.value?.trim();
                        const label = input.closest('label')?.querySelector('span')?.textContent?.trim() || input.name || 'Archivo';

                        items.push({
                            file,
                            label: trackName && input.name.startsWith('track_audio_files') ? `${trackName} audio` : label,
                            size: file.size || 0,
                            offset: 0,
                            row: null,
                            bar: null,
                            percent: null,
                            status: null,
                        });
                    });
                });

                let offset = 0;

                items.forEach((item) => {
                    item.offset = offset;
                    offset += item.size;
                });

                return {items, totalSize: offset};
            };

            const setSubmitState = (form, isSubmitting, submitter) => {
                const buttons = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));

                buttons.forEach((button) => {
                    if (isSubmitting) {
                        button.dataset.uploadWasDisabled = button.disabled ? 'true' : 'false';
                        button.disabled = true;
                    } else {
                        button.disabled = button.dataset.uploadWasDisabled === 'true';
                        delete button.dataset.uploadWasDisabled;
                    }
                });

                if (!submitter) return;

                if (isSubmitting) {
                    submitter.dataset.uploadOriginalText = submitter.textContent;
                    submitter.textContent = 'Subiendo...';
                } else if (submitter.dataset.uploadOriginalText) {
                    submitter.textContent = submitter.dataset.uploadOriginalText;
                    delete submitter.dataset.uploadOriginalText;
                }
            };

            const progressElements = (form) => {
                const panel = form.querySelector('[data-upload-progress]');

                return {
                    panel,
                    state: panel?.querySelector('[data-upload-state-label]'),
                    message: panel?.querySelector('[data-upload-message]'),
                    percent: panel?.querySelector('[data-upload-percent]'),
                    track: panel?.querySelector('.upload-progress-track'),
                    bar: panel?.querySelector('[data-upload-progress-bar]'),
                    fileList: panel?.querySelector('[data-upload-file-list]'),
                    cancel: panel?.querySelector('[data-upload-cancel]'),
                    retry: panel?.querySelector('[data-upload-retry]'),
                };
            };

            const renderFileList = (elements, upload) => {
                elements.fileList.replaceChildren();

                if (upload.items.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'upload-progress-empty';
                    empty.textContent = 'Sin archivos nuevos para subir.';
                    elements.fileList.append(empty);
                    return;
                }

                upload.items.forEach((item) => {
                    const row = document.createElement('div');
                    row.className = 'upload-progress-file';

                    const meta = document.createElement('div');
                    const name = document.createElement('strong');
                    const detail = document.createElement('span');
                    const status = document.createElement('span');

                    name.textContent = item.file.name;
                    detail.textContent = `${item.label} - ${formatBytes(item.size)}`;
                    status.textContent = 'En espera';

                    meta.append(name, detail);

                    const track = document.createElement('div');
                    track.className = 'upload-progress-file-track';
                    const bar = document.createElement('span');
                    track.append(bar);

                    row.append(meta, status, track);
                    elements.fileList.append(row);

                    item.row = row;
                    item.bar = bar;
                    item.percent = detail;
                    item.status = status;
                });
            };

            const setFileProgress = (upload, percent, state) => {
                const uploadedBytes = upload.totalSize > 0 ? (percent / 100) * upload.totalSize : 0;

                upload.items.forEach((item) => {
                    let itemPercent = percent;

                    if (upload.totalSize > 0 && item.size > 0) {
                        itemPercent = clampPercent(((uploadedBytes - item.offset) / item.size) * 100);
                    }

                    if (state === 'success') itemPercent = 100;

                    item.bar.style.width = `${itemPercent}%`;

                    if (state === 'error') {
                        item.status.textContent = itemPercent >= 100 ? 'Completado' : 'Error';
                    } else if (state === 'canceled') {
                        item.status.textContent = itemPercent >= 100 ? 'Completado' : 'Cancelado';
                    } else if (itemPercent >= 100) {
                        item.status.textContent = 'Completado';
                    } else if (itemPercent > 0) {
                        item.status.textContent = `En progreso ${itemPercent}%`;
                    } else {
                        item.status.textContent = 'En espera';
                    }
                });
            };

            const setProgress = (elements, upload, state, percent, message) => {
                const safePercent = clampPercent(percent);
                const stateLabel = {
                    running: 'En progreso',
                    success: 'Completado',
                    error: 'Error',
                    canceled: 'Cancelado',
                }[state] || 'En progreso';

                elements.panel.hidden = false;
                elements.panel.dataset.uploadState = state;
                elements.state.textContent = stateLabel;
                elements.message.textContent = message;
                elements.percent.textContent = `${safePercent}%`;
                elements.bar.style.width = `${safePercent}%`;
                elements.track.setAttribute('aria-valuenow', String(safePercent));
                setFileProgress(upload, safePercent, state);
            };

            const initializeAlbumUploadProgress = () => {
                document.querySelectorAll(`[data-album-upload-progress-form]:not([${readyAttribute}])`).forEach((form) => {
                    form.setAttribute(readyAttribute, 'true');

                    let currentXhr = null;
                    let clickedSubmitter = null;
                    let lastSubmitter = null;

                    form.addEventListener('click', (event) => {
                        const submitter = event.target.closest('button[type="submit"], input[type="submit"]');
                        if (submitter) clickedSubmitter = submitter;
                    });

                    const startUpload = (submitter) => {
                        if (currentXhr || !submitter) return;
                        if (typeof form.reportValidity === 'function' && !form.reportValidity()) return;

                        const elements = progressElements(form);
                        if (!elements.panel || typeof XMLHttpRequest === 'undefined' || typeof FormData === 'undefined') return;

                        lastSubmitter = submitter;
                        const upload = uploadItemsFor(form);
                        const formData = new FormData(form);

                        if (submitter.name) {
                            formData.set(submitter.name, submitter.value);
                        }

                        renderFileList(elements, upload);
                        elements.cancel.hidden = false;
                        elements.retry.hidden = true;
                        setProgress(elements, upload, 'running', 0, 'Preparando upload.');
                        setSubmitState(form, true, submitter);

                        const xhr = new XMLHttpRequest();
                        let canceled = false;
                        let finished = false;
                        let latestPercent = 0;

                        currentXhr = xhr;

                        const finish = (callback) => {
                            if (finished) return;

                            finished = true;
                            currentXhr = null;
                            setSubmitState(form, false, submitter);
                            callback();
                        };

                        elements.cancel.onclick = () => {
                            if (!currentXhr) return;

                            canceled = true;
                            currentXhr.abort();
                        };

                        elements.retry.onclick = () => {
                            elements.retry.hidden = true;
                            startUpload(lastSubmitter);
                        };

                        xhr.upload.addEventListener('progress', (event) => {
                            if (!event.lengthComputable || event.total <= 0) {
                                latestPercent = Math.max(latestPercent, 1);
                                setProgress(elements, upload, 'running', latestPercent, 'Upload en progreso.');
                                return;
                            }

                            const percent = Math.min(99, clampPercent((event.loaded / event.total) * 100));
                            latestPercent = percent;
                            setProgress(elements, upload, 'running', percent, `Subiendo archivos ${percent}%.`);
                        });

                        xhr.onreadystatechange = () => {
                            if (xhr.readyState !== XMLHttpRequest.DONE || canceled) return;

                            if (xhr.status >= 200 && xhr.status < 300) {
                                const message = responseMessage(xhr, 'Album publicado correctamente.');

                                finish(() => {
                                    elements.cancel.hidden = true;
                                    elements.retry.hidden = true;
                                    setProgress(elements, upload, 'success', 100, message);

                                    window.setTimeout(() => {
                                        window.location.assign(form.dataset.uploadSuccessUrl || window.location.href);
                                    }, 700);
                                });

                                return;
                            }

                            finish(() => {
                                elements.cancel.hidden = true;
                                elements.retry.hidden = false;
                                setProgress(elements, upload, 'error', latestPercent, responseMessage(xhr, 'No se pudo completar el upload. Reintenta.'));
                            });
                        };

                        xhr.onerror = () => {
                            finish(() => {
                                elements.cancel.hidden = true;
                                elements.retry.hidden = false;
                                setProgress(elements, upload, 'error', latestPercent, 'Fallo de red durante el upload. Reintenta.');
                            });
                        };

                        xhr.ontimeout = () => {
                            finish(() => {
                                elements.cancel.hidden = true;
                                elements.retry.hidden = false;
                                setProgress(elements, upload, 'error', latestPercent, 'El upload tardo demasiado. Reintenta con una conexion estable.');
                            });
                        };

                        xhr.onabort = () => {
                            finish(() => {
                                elements.cancel.hidden = true;
                                elements.retry.hidden = false;
                                setProgress(elements, upload, 'canceled', latestPercent, 'Upload cancelado.');
                            });
                        };

                        xhr.open((form.getAttribute('method') || 'POST').toUpperCase(), form.action, true);
                        xhr.timeout = 600000;
                        xhr.setRequestHeader('Accept', 'application/json');
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.send(formData);
                    };

                    form.addEventListener('submit', (event) => {
                        const submitter = event.submitter || clickedSubmitter;

                        if (!submitter || submitter.value !== 'publish') return;
                        if (typeof XMLHttpRequest === 'undefined' || typeof FormData === 'undefined') return;
                        if (!form.querySelector('[data-upload-progress]')) return;

                        event.preventDefault();
                        startUpload(submitter);
                    });
                });
            };

            initializeAlbumUploadProgress();
        })();
    </script>
@endonce
