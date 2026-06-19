@props([
    'title',
    'copy',
    'action' => 'Abrir',
    'tone' => 'neutral',
])

<div class="admin-page-heading">
    <div>
        <h1>{{ $title }}</h1>
        <p>{{ $copy }}</p>
    </div>
    <button
        class="admin-button {{ $tone === 'royal' ? 'admin-button-warning' : 'admin-button-primary' }}"
        type="button"
        data-admin-toast="{{ $title }}|Accion preparada para esta seccion.|info"
    >
        {{ $action }}
    </button>
</div>

<div class="admin-mock-grid">
    <article class="admin-panel">
        <div class="admin-section-head">
            <div>
                <p class="admin-kicker">Estado</p>
                <h2>Modulo listo</h2>
            </div>
            <span class="admin-status-pill admin-status-success">Activo</span>
        </div>
        <p class="admin-panel-copy">Esta seccion mantiene el comportamiento visual del wireframe mientras el backend dedicado queda conectado por fases.</p>
    </article>

    <article class="admin-panel">
        <div class="admin-list">
            <div class="admin-list-row">
                <span>1</span>
                <strong>Revision pendiente</strong>
                <em>Hoy</em>
            </div>
            <div class="admin-list-row">
                <span>2</span>
                <strong>Automatizacion activa</strong>
                <em>OK</em>
            </div>
            <div class="admin-list-row">
                <span>3</span>
                <strong>Sin errores criticos</strong>
                <em>Stable</em>
            </div>
        </div>
    </article>
</div>
