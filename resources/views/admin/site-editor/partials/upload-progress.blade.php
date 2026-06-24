<section class="upload-progress-panel" data-upload-progress hidden aria-live="polite" aria-label="{{ $label ?? 'Upload progress' }}">
    <div class="upload-progress-head">
        <div>
            <span data-upload-state-label>En progreso</span>
            <strong data-upload-message>Preparando upload.</strong>
        </div>
        <strong data-upload-percent>0%</strong>
    </div>

    <div class="upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <span data-upload-progress-bar></span>
    </div>

    <div class="upload-progress-files" data-upload-file-list></div>

    <div class="upload-progress-actions">
        <button class="admin-button admin-button-ghost" type="button" data-upload-cancel>Cancelar</button>
        <button class="admin-button admin-button-soft" type="button" data-upload-retry hidden>Reintentar</button>
    </div>
</section>
