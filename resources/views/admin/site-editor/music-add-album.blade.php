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
    data-track-upload-url="{{ route('admin.content.album-track-audio.store') }}"
    data-upload-success-url="{{ route('admin.site-editor.show', ['page' => 'music']) }}"
    data-max-tracks="30"
    data-max-track-file-size="{{ 50 * 1024 * 1024 }}"
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
            const trackFileInputSelector = 'input[type="file"][name^="track_audio_files"]';

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

            const csrfToken = (form) => (
                form.querySelector('input[name="_token"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.content
                || ''
            );

            const trackRows = (form) => Array.from(form.querySelectorAll('[data-track-row]'));

            const trackNameFor = (row) => row.querySelector('[name$="[track_name]"]')?.value?.trim() || 'Track';

            const ensureTrackAssetInput = (row, index) => {
                let input = row.querySelector('[name$="[track_audio_asset_id]"]');

                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    row.append(input);
                }

                input.name = `metadata[tracks][${index}][track_audio_asset_id]`;

                return input;
            };

            const uploadItemsFor = (form) => {
                const items = [];

                trackRows(form).forEach((row, index) => {
                    const input = row.querySelector(trackFileInputSelector);
                    const file = input?.files?.[0];

                    if (!file) return;

                    items.push({
                        index,
                        rowElement: row,
                        input,
                        file,
                        trackName: trackNameFor(row),
                        size: file.size || 0,
                        loaded: 0,
                        state: 'pending',
                        element: null,
                        bar: null,
                        detail: null,
                        status: null,
                    });
                });

                return {
                    items,
                    totalSize: items.reduce((total, item) => total + item.size, 0),
                };
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
                    detail.textContent = `${item.trackName} - ${formatBytes(item.size)}`;
                    status.textContent = 'En espera';

                    meta.append(name, detail);

                    const track = document.createElement('div');
                    track.className = 'upload-progress-file-track';
                    const bar = document.createElement('span');
                    track.append(bar);

                    row.append(meta, status, track);
                    elements.fileList.append(row);

                    item.element = row;
                    item.bar = bar;
                    item.detail = detail;
                    item.status = status;
                });
            };

            const setFileProgress = (item, percent, state, message = null) => {
                const safePercent = clampPercent(percent);

                if (!item.bar || !item.status) return;

                item.state = state;
                item.bar.style.width = `${safePercent}%`;
                item.element?.classList.toggle('is-success', state === 'success');
                item.element?.classList.toggle('is-error', state === 'error');

                if (message) {
                    item.status.textContent = message;
                } else if (state === 'success') {
                    item.status.textContent = 'Completado';
                } else if (state === 'error') {
                    item.status.textContent = 'Error';
                } else if (state === 'running') {
                    item.status.textContent = safePercent > 0 ? `En progreso ${safePercent}%` : 'En progreso';
                } else {
                    item.status.textContent = 'En espera';
                }
            };

            const uploadedBytesFor = (upload) => upload.items.reduce((total, item) => {
                if (item.state === 'success') return total + item.size;

                return total + Math.min(item.loaded || 0, item.size);
            }, 0);

            const uploadPercent = (upload, cap = 95) => {
                if (upload.totalSize <= 0) return 0;

                return Math.min(cap, clampPercent((uploadedBytesFor(upload) / upload.totalSize) * cap));
            };

            const setProgress = (elements, state, percent, message) => {
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
            };

            const finalAlbumFormData = (form, submitter) => {
                const formData = new FormData(form);

                Array.from(formData.keys()).forEach((key) => {
                    if (key.startsWith('track_audio_files[')) {
                        formData.delete(key);
                    }
                });

                if (submitter?.name) {
                    formData.set(submitter.name, submitter.value);
                }

                return formData;
            };

            const uploadTrack = (form, item, upload, elements, onRequest) => new Promise((resolve) => {
                const maxFileSize = Number(form.dataset.maxTrackFileSize || 0);

                if (maxFileSize > 0 && item.size > maxFileSize) {
                    item.loaded = 0;
                    setFileProgress(item, 0, 'error', `Excede ${formatBytes(maxFileSize)}`);
                    resolve({ok: false, message: `${item.trackName}: excede ${formatBytes(maxFileSize)}.`});
                    return;
                }

                const xhr = new XMLHttpRequest();
                const formData = new FormData();
                const token = csrfToken(form);

                formData.set('album_title', form.querySelector('[name="title"]')?.value || '');
                formData.set('track_name', item.trackName);
                formData.set('track_index', String(item.index));
                formData.set('track_audio_file', item.file);

                if (token) {
                    formData.set('_token', token);
                }

                onRequest(xhr);
                setFileProgress(item, 0, 'running');
                setProgress(elements, 'running', uploadPercent(upload), `Subiendo ${item.trackName}.`);

                xhr.upload.addEventListener('progress', (event) => {
                    if (!event.lengthComputable || event.total <= 0) {
                        item.loaded = Math.max(item.loaded, 1);
                        setFileProgress(item, 1, 'running');
                        setProgress(elements, 'running', Math.max(uploadPercent(upload), 1), `Subiendo ${item.trackName}.`);
                        return;
                    }

                    item.loaded = Math.min(event.loaded, item.size);
                    const itemPercent = clampPercent((event.loaded / event.total) * 100);
                    setFileProgress(item, Math.min(itemPercent, 99), 'running');
                    setProgress(elements, 'running', uploadPercent(upload), `Subiendo ${item.trackName}.`);
                });

                xhr.onreadystatechange = () => {
                    if (xhr.readyState !== XMLHttpRequest.DONE) return;

                    if (xhr.status >= 200 && xhr.status < 300) {
                        const payload = parseJson(xhr.responseText || '{}');
                        const assetId = payload.asset?.id;

                        if (!assetId) {
                            setFileProgress(item, clampPercent((item.loaded / Math.max(item.size, 1)) * 100), 'error', 'Error');
                            resolve({ok: false, message: `${item.trackName}: el servidor no devolvio el asset.`});
                            return;
                        }

                        const input = ensureTrackAssetInput(item.rowElement, item.index);
                        input.value = String(assetId);

                        item.loaded = item.size;
                        setFileProgress(item, 100, 'success');
                        item.input.required = false;
                        item.input.value = '';
                        setProgress(elements, 'running', uploadPercent(upload), `${item.trackName} completado.`);
                        resolve({ok: true});
                        return;
                    }

                    const message = responseMessage(xhr, `${item.trackName}: no se pudo subir.`);
                    setFileProgress(item, clampPercent((item.loaded / Math.max(item.size, 1)) * 100), 'error', 'Error');
                    resolve({ok: false, message});
                };

                xhr.onerror = () => {
                    setFileProgress(item, clampPercent((item.loaded / Math.max(item.size, 1)) * 100), 'error', 'Error');
                    resolve({ok: false, message: `${item.trackName}: fallo de red.`});
                };

                xhr.ontimeout = () => {
                    setFileProgress(item, clampPercent((item.loaded / Math.max(item.size, 1)) * 100), 'error', 'Timeout');
                    resolve({ok: false, message: `${item.trackName}: el upload tardo demasiado.`});
                };

                xhr.onabort = () => {
                    setFileProgress(item, clampPercent((item.loaded / Math.max(item.size, 1)) * 100), 'error', 'Cancelado');
                    resolve({ok: false, canceled: true, message: `${item.trackName}: cancelado.`});
                };

                xhr.open('POST', form.dataset.trackUploadUrl, true);
                xhr.timeout = 300000;
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                if (token) {
                    xhr.setRequestHeader('X-CSRF-TOKEN', token);
                }

                xhr.send(formData);
            });

            const submitAlbum = (form, submitter, elements, upload, onRequest) => new Promise((resolve) => {
                const xhr = new XMLHttpRequest();
                let latestPercent = Math.max(uploadPercent(upload), upload.items.length > 0 ? 95 : 0);

                onRequest(xhr);
                setProgress(elements, 'running', latestPercent, 'Guardando album.');

                xhr.upload.addEventListener('progress', (event) => {
                    if (!event.lengthComputable || event.total <= 0) {
                        latestPercent = Math.max(latestPercent, 96);
                        setProgress(elements, 'running', latestPercent, 'Guardando album.');
                        return;
                    }

                    latestPercent = Math.max(latestPercent, Math.min(99, 95 + clampPercent((event.loaded / event.total) * 4)));
                    setProgress(elements, 'running', latestPercent, 'Guardando album.');
                });

                xhr.onreadystatechange = () => {
                    if (xhr.readyState !== XMLHttpRequest.DONE) return;

                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve({ok: true, message: responseMessage(xhr, 'Album guardado correctamente.')});
                        return;
                    }

                    resolve({ok: false, message: responseMessage(xhr, 'No se pudo guardar el album. Reintenta.')});
                };

                xhr.onerror = () => resolve({ok: false, message: 'Fallo de red guardando el album. Reintenta.'});
                xhr.ontimeout = () => resolve({ok: false, message: 'Guardar el album tardo demasiado. Reintenta.'});
                xhr.onabort = () => resolve({ok: false, canceled: true, message: 'Upload cancelado.'});

                xhr.open((form.getAttribute('method') || 'POST').toUpperCase(), form.action, true);
                xhr.timeout = 300000;
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send(finalAlbumFormData(form, submitter));
            });

            const initializeAlbumUploadProgress = () => {
                document.querySelectorAll(`[data-album-upload-progress-form]:not([${readyAttribute}])`).forEach((form) => {
                    form.setAttribute(readyAttribute, 'true');

                    let activeXhr = null;
                    let isSubmitting = false;
                    let clickedSubmitter = null;
                    let lastSubmitter = null;

                    form.addEventListener('click', (event) => {
                        const submitter = event.target.closest('button[type="submit"], input[type="submit"]');
                        if (submitter) clickedSubmitter = submitter;
                    });

                    const startUpload = async (submitter) => {
                        if (isSubmitting || !submitter) return;
                        const elements = progressElements(form);
                        if (!elements.panel || typeof XMLHttpRequest === 'undefined' || typeof FormData === 'undefined') return;

                        const rows = trackRows(form);
                        const maxTracks = Number(form.dataset.maxTracks || 0);
                        const emptyUpload = {items: [], totalSize: 0};

                        if (maxTracks > 0 && rows.length > maxTracks) {
                            renderFileList(elements, emptyUpload);
                            elements.cancel.hidden = true;
                            elements.retry.hidden = true;
                            setProgress(elements, 'error', 0, `El album puede tener maximo ${maxTracks} canciones.`);
                            return;
                        }

                        if (typeof form.reportValidity === 'function' && !form.reportValidity()) return;

                        isSubmitting = true;
                        lastSubmitter = submitter;
                        const upload = uploadItemsFor(form);
                        let canceled = false;

                        renderFileList(elements, upload);
                        elements.cancel.hidden = false;
                        elements.retry.hidden = true;
                        setProgress(elements, 'running', 0, upload.items.length > 0 ? 'Preparando canciones.' : 'Guardando album.');
                        setSubmitState(form, true, submitter);

                        elements.cancel.onclick = () => {
                            canceled = true;
                            activeXhr?.abort();
                        };

                        elements.retry.onclick = () => {
                            elements.retry.hidden = true;
                            startUpload(lastSubmitter);
                        };

                        const failures = [];

                        for (const item of upload.items) {
                            if (canceled) break;

                            const result = await uploadTrack(form, item, upload, elements, (xhr) => {
                                activeXhr = xhr;
                            });

                            activeXhr = null;

                            if (result.canceled || canceled) {
                                canceled = true;
                                break;
                            }

                            if (!result.ok) {
                                failures.push(result.message);
                            }
                        }

                        if (canceled) {
                            elements.cancel.hidden = true;
                            elements.retry.hidden = false;
                            setSubmitState(form, false, submitter);
                            setProgress(elements, 'canceled', uploadPercent(upload), 'Upload cancelado.');
                            isSubmitting = false;
                            return;
                        }

                        if (failures.length > 0) {
                            elements.cancel.hidden = true;
                            elements.retry.hidden = false;
                            setSubmitState(form, false, submitter);
                            setProgress(elements, 'error', uploadPercent(upload), `${failures.length} cancion(es) fallaron. ${failures[0]} Las demas quedaron subidas; corrige y reintenta.`);
                            isSubmitting = false;
                            return;
                        }

                        const result = await submitAlbum(form, submitter, elements, upload, (xhr) => {
                            activeXhr = xhr;
                        });

                        activeXhr = null;
                        elements.cancel.hidden = true;
                        setSubmitState(form, false, submitter);
                        isSubmitting = false;

                        if (result.canceled) {
                            elements.retry.hidden = false;
                            setProgress(elements, 'canceled', uploadPercent(upload), result.message);
                            return;
                        }

                        if (!result.ok) {
                            elements.retry.hidden = false;
                            setProgress(elements, 'error', Math.max(uploadPercent(upload), 95), result.message);
                            return;
                        }

                        elements.retry.hidden = true;
                        setProgress(elements, 'success', 100, result.message);

                        window.setTimeout(() => {
                            window.location.assign(form.dataset.uploadSuccessUrl || window.location.href);
                        }, 700);
                    };

                    form.addEventListener('submit', (event) => {
                        const submitter = event.submitter || clickedSubmitter;

                        if (!submitter || !submitter.name) return;
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
