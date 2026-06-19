@extends('admin.layout')

@section('title', 'Dashboard editorial')
@section('admin_section', 'dashboard')
@section('admin_theme', 'neutral')

@section('content')
    <section id="sec-dashboard" class="admin-dashboard-section is-active" data-admin-section-panel="dashboard">
        <div class="page-hero admin-hero-row">
            <div>
                <h1>Dashboard editorial</h1>
                <p>Control rapido de contenido, acceso Royal, eventos y comunidad.</p>
            </div>
            <a class="admin-button admin-button-primary" href="{{ route('admin.content.create') }}">
                <span aria-hidden="true">+</span>
                <span>Nuevo contenido</span>
            </a>
        </div>

        <div class="admin-alert-card admin-alert-warning">
            <div>
                <span class="admin-alert-symbol" aria-hidden="true">!</span>
                <div>
                    <h2>Cosas urgentes que necesitan tu atencion</h2>
                    <p>Tienes {{ $stats['draftContent'] }} borradores, {{ $stats['scheduledContent'] }} publicaciones programadas y pagos para revisar.</p>
                </div>
            </div>
            <div class="admin-actions">
                <a class="admin-button admin-button-warning" href="#pagos" data-admin-nav="pagos">Revisar Pagos</a>
                <a class="admin-button admin-button-ghost" href="{{ route('admin.content.index') }}">Ver contenido</a>
            </div>
        </div>

        <div class="admin-metric-grid">
            <article class="admin-metric-card">
                <span class="admin-metric-icon" aria-hidden="true">♛</span>
                <p>Miembros Royal activos</p>
                <strong>{{ number_format($stats['royalActive']) }}</strong>
                <small>Acceso premium habilitado</small>
            </article>
            <article class="admin-metric-card">
                <span class="admin-metric-icon" aria-hidden="true">▤</span>
                <p>Publicaciones activas</p>
                <strong>{{ number_format($stats['publishedContent']) }}</strong>
                <small>{{ number_format($stats['scheduledContent']) }} programadas</small>
            </article>
            <article class="admin-metric-card">
                <span class="admin-metric-icon" aria-hidden="true">$</span>
                <p>Ventas de este mes</p>
                <strong>${{ number_format($stats['monthlySales'], 2) }} USD</strong>
                <small>Ordenes completadas</small>
            </article>
            <article class="admin-metric-card">
                <span class="admin-metric-icon" aria-hidden="true">✉</span>
                <p>Usuarios registrados</p>
                <strong>{{ number_format($stats['users']) }}</strong>
                <small>{{ number_format($stats['mediaAssets']) }} assets cargados</small>
            </article>
        </div>

        <div class="admin-two-column">
            <article class="admin-panel">
                <div class="admin-section-head">
                    <div>
                        <p class="admin-kicker">Proximo evento</p>
                        <h2>En vivo acustico exclusivo</h2>
                    </div>
                    <span class="admin-status-pill admin-status-info">Tickets</span>
                </div>

                @forelse ($upcomingEvents as $event)
                    <div class="admin-event-card">
                        <div class="admin-event-date">
                            <strong>{{ $event->starts_at->timezone($event->timezone)->format('d') }}</strong>
                            <span>{{ $event->starts_at->timezone($event->timezone)->format('M') }}</span>
                        </div>
                        <div>
                            <h3>{{ $event->title }}</h3>
                            <p>{{ $event->venue ?: 'Evento digital' }} · {{ $event->starts_at->timezone($event->timezone)->format('g:i A') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="admin-event-card">
                        <div class="admin-event-date">
                            <strong>20</strong>
                            <span>Jun</span>
                        </div>
                        <div>
                            <h3>En vivo acustico exclusivo</h3>
                            <p>Transmision online · 8:00 PM</p>
                        </div>
                    </div>
                @endforelse

                <a class="admin-inline-link" href="#eventos" data-admin-nav="eventos">Ver lista de asistentes y tickets →</a>
            </article>

            <article class="admin-panel">
                <div class="admin-section-head">
                    <div>
                        <p class="admin-kicker">Borrador rapido</p>
                        <h2>Que estas pensando hoy?</h2>
                    </div>
                </div>

                <form class="admin-quick-draft" method="POST" action="{{ route('admin.content.store') }}">
                    @csrf
                    <input name="action" type="hidden" value="draft">
                    <input name="type" type="hidden" value="post">
                    <input name="visibility" type="hidden" value="open">
                    <input name="body" type="hidden" value="Draft created from the dashboard quick capture.">

                    <label>
                        <span class="sr-only">Titulo del borrador</span>
                        <input id="quickPostTitle" name="title" type="text" placeholder="Escribe el titulo aqui..." required>
                    </label>

                    <div class="admin-form-footer">
                        <small>Se guardara como borrador para editarlo despues.</small>
                        <button class="admin-button admin-button-primary" type="submit">Guardar Borrador rapido</button>
                    </div>
                </form>
            </article>
        </div>
    </section>

    <section id="sec-banners" class="admin-dashboard-section" data-admin-section-panel="banners">
        <div class="page-hero admin-hero-row">
            <div>
                <p class="admin-kicker">Website principal</p>
                <h1>Banners</h1>
                <p>Gestiona los banners principales del website publico con el mismo look de RenyRenteria.com.</p>
            </div>
            <a class="admin-button admin-button-primary" href="{{ route('admin.site-editor.show', ['page' => 'home']) }}">
                <span>Editar portada</span>
            </a>
        </div>

        <div class="admin-two-column">
            <article class="admin-panel">
                <div class="admin-section-head">
                    <div>
                        <p class="admin-kicker">Hero activo</p>
                        <h2>Biggest Launch</h2>
                    </div>
                    <span class="admin-status-pill admin-status-info">Home</span>
                </div>

                <div class="admin-preview-tile admin-banner-preview">
                    <div>RR</div>
                    <h3>Biggest Launch</h3>
                    <p>Comeback Album!</p>
                    <footer>
                        <span>Visible</span>
                        <small>Publico</small>
                    </footer>
                </div>
            </article>

            <article class="admin-panel">
                <div class="admin-section-head">
                    <div>
                        <p class="admin-kicker">Siguientes bloques</p>
                        <h2>Portada y secciones destacadas</h2>
                    </div>
                </div>

                <div class="admin-list">
                    <div class="admin-list-row">
                        <span>1</span>
                        <strong>Hero principal del home</strong>
                        <em>Conectado al Site Editor</em>
                    </div>
                    <div class="admin-list-row">
                        <span>2</span>
                        <strong>Banner de videos</strong>
                        <em>Fase siguiente</em>
                    </div>
                    <div class="admin-list-row">
                        <span>3</span>
                        <strong>Banner de store</strong>
                        <em>Fase siguiente</em>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section id="sec-contenido" class="admin-dashboard-section" data-admin-section-panel="contenido">
        <div class="admin-page-heading">
            <div>
                <h1>Contenido de tu Sitio</h1>
                <p>Aqui organizas la musica, videos, fotos y noticias que tus fans pueden ver.</p>
            </div>
            <a class="admin-button admin-button-primary" href="{{ route('admin.content.create') }}">+ Subir o escribir algo nuevo</a>
        </div>

        @include('admin.partials.content-cards', ['contents' => $queueItems])
    </section>

    <section id="sec-editor" class="admin-dashboard-section" data-admin-section-panel="editor">
        <div class="admin-page-heading">
            <div>
                <h1>Crear o Editar Contenido</h1>
                <p>Completa estos pasos para publicar nuevo material.</p>
            </div>
            <a class="admin-button admin-button-ghost" href="{{ route('admin.content.index') }}">Cancelar y volver</a>
        </div>

        <div class="admin-editor-grid">
            <form class="admin-panel admin-content-form" method="POST" action="{{ route('admin.content.store') }}">
                @csrf
                <input name="type" type="hidden" value="post">

                <div class="admin-form-grid admin-form-grid-wide">
                    <label>
                        <span>Titulo de la publicacion *</span>
                        <input id="postTitle" name="title" type="text" maxlength="160" placeholder="Ej: Mi nuevo video musical exclusivo" required>
                    </label>

                    <label>
                        <span>Quien puede ver esto? *</span>
                        <select id="postAccess" name="visibility" required>
                            <option value="open">Todo el mundo (Publico / Gratis)</option>
                            <option value="royal">Solo miembros de Royal Pass</option>
                            <option value="purchased">Compra Individual</option>
                        </select>
                    </label>

                    <label class="admin-field-wide">
                        <span>Descripcion breve *</span>
                        <textarea id="postDesc" name="summary" rows="3" maxlength="500" placeholder="Cuentales a tus fans de que trata esto..."></textarea>
                    </label>

                    <label class="admin-field-wide">
                        <span>Contenido *</span>
                        <textarea name="body" rows="8" required></textarea>
                    </label>

                    <label>
                        <span>Cuando se debe publicar?</span>
                        <select name="action" data-admin-action-select>
                            <option value="draft">Guardar como borrador</option>
                            @if ($canPublish)
                                <option value="publish">Publicar ahora mismo</option>
                                <option value="schedule">Programar fecha y hora</option>
                            @endif
                        </select>
                    </label>

                    <label data-admin-schedule-field hidden>
                        <span>Fecha programada</span>
                        <input name="scheduled_at" type="datetime-local">
                    </label>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button admin-button-primary" type="submit">Guardar</button>
                    <a class="admin-button admin-button-ghost" href="{{ route('admin.content.create') }}">Abrir editor avanzado</a>
                </div>
            </form>

            <aside class="admin-panel admin-live-preview" aria-label="Vista previa rapida">
                <h2>Vista Previa Rapida</h2>
                <div class="admin-preview-tile">
                    <div>Sin imagen asignada</div>
                    <h3 id="previewTitleDisplay">Titulo de prueba</h3>
                    <p id="previewDescDisplay">Descripcion de prueba...</p>
                    <footer>
                        <span id="previewAccessDisplay">Libre</span>
                        <small>Borrador</small>
                    </footer>
                </div>
                <button class="admin-button admin-button-primary" type="button" data-admin-toast="Publicacion|Completa el formulario para publicar o guardar.|info">Ver antes de publicar</button>
            </aside>
        </div>
    </section>

    <section id="sec-biblioteca" class="admin-dashboard-section" data-admin-section-panel="biblioteca">
        <div class="admin-page-heading">
            <div>
                <h1>Biblioteca de Fotos y Videos</h1>
                <p>Administra todos los archivos cargados al sitio web.</p>
            </div>
            <a class="admin-button admin-button-primary" href="{{ route('admin.media.index') }}">+ Subir Nuevo Archivo</a>
        </div>

        <div class="admin-media-grid">
            @forelse ($recentAssets as $asset)
                <article class="admin-media-card">
                    <div class="admin-media-thumb">{{ strtoupper(str_replace('_', ' ', $asset->type->value)) }}</div>
                    <h3>{{ $asset->title ?: $asset->original_filename }}</h3>
                    <p>{{ $asset->original_filename }} · {{ $asset->created_at->format('d M Y') }}</p>
                    <footer>
                        <span>{{ $asset->processing_status->value }}</span>
                        <button type="button" data-admin-toast="Copiado|Enlace copiado al portapapeles.|info">Copiar Link</button>
                    </footer>
                </article>
            @empty
                <article class="admin-empty-state">No media assets yet.</article>
            @endforelse
        </div>
    </section>

    <section id="sec-productos" class="admin-dashboard-section" data-admin-section-panel="productos">
        <x-admin.mock-section
            title="Productos y Desbloqueos"
            copy="Vende productos digitales u ofrece accesos premium a tu comunidad."
            action="Agregar Nuevo Producto"
        />
    </section>

    <section id="sec-royalpass" class="admin-dashboard-section" data-admin-section-panel="royalpass">
        <div class="admin-page-heading">
            <div>
                <h1>Membresia: Royal Pass</h1>
                <p>Aqui manejas a tus fans mas valiosos que pagan cada mes.</p>
            </div>
            <button class="admin-button admin-button-warning" type="button" data-admin-toast="Configuracion guardada|El precio del Royal Pass se actualizo.|success">Ajustes de Cobro</button>
        </div>

        <div class="admin-metric-grid">
            <article class="admin-state-card success"><span>Activo</span><strong>{{ number_format($stats['royalActive']) }} fans</strong><small>Tiene acceso</small></article>
            <article class="admin-state-card warning"><span>Periodo de gracia</span><strong>0 fans</strong><small>Reintentando pago</small></article>
            <article class="admin-state-card muted"><span>Expirado / Cancelado</span><strong>0 fans</strong><small>Acceso revocado</small></article>
            <article class="admin-state-card danger"><span>Devolucion</span><strong>0 fans</strong><small>Revocado al instante</small></article>
        </div>
    </section>

    <section id="sec-usuarios" class="admin-dashboard-section" data-admin-section-panel="usuarios">
        <div class="admin-page-heading">
            <div>
                <h1>Fans y Usuarios</h1>
                <p>Lista de personas registradas en tu web.</p>
            </div>
        </div>

        <div class="admin-table-card">
            <table>
                <thead>
                    <tr>
                        <th>Fan</th>
                        <th>Username</th>
                        <th>Membresia</th>
                        <th>Rol</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentUsers as $fan)
                        <tr>
                            <td>
                                <strong>{{ $fan->name }}</strong>
                                <small>{{ $fan->email }}</small>
                            </td>
                            <td>{{ $fan->username ? '@'.$fan->username : 'Sin username' }}</td>
                            <td>{{ str_replace('_', ' ', $fan->royal_status) }}</td>
                            <td>{{ str_replace('_', ' ', $fan->role) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No users yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section id="sec-comunidad" class="admin-dashboard-section" data-admin-section-panel="comunidad">
        <x-admin.mock-section
            title="Foro y Comentarios de Fans"
            copy="Aprueba o borra lo que los fans comentan en el sitio web."
            action="Aprobar comentario"
            tone="community"
        />
    </section>

    <section id="sec-eventos" class="admin-dashboard-section" data-admin-section-panel="eventos">
        <x-admin.mock-section
            title="Eventos y Boletos"
            copy="Crea eventos y controla quien tiene su boleto pagado."
            action="Crear Nuevo Evento"
            tone="events"
        />
    </section>

    <section id="sec-puntos" class="admin-dashboard-section" data-admin-section-panel="puntos">
        <div class="admin-page-heading">
            <div>
                <h1>Puntos y Tabla de Ranking</h1>
                <p>Mira quienes son tus fans mas activos.</p>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-list">
                @forelse ($topFans as $entry)
                    <div class="admin-list-row">
                        <span>{{ $loop->iteration }}</span>
                        <strong>{{ $entry->user?->name ?? 'Fan' }}</strong>
                        <em>{{ number_format($entry->balance_after) }} puntos</em>
                    </div>
                @empty
                    <div class="admin-empty-state">No point activity yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section id="sec-pagos" class="admin-dashboard-section" data-admin-section-panel="pagos">
        <x-admin.mock-section
            title="Pagos y Devoluciones"
            copy="Revisa cobros, refunds y accesos revocados automaticamente."
            action="Revisar transaccion"
            tone="royal"
        />
    </section>

    <section id="sec-notificaciones" class="admin-dashboard-section" data-admin-section-panel="notificaciones">
        <div class="admin-page-heading">
            <div>
                <h1>Anuncios y Mensajes</h1>
                <p>Prepara mensajes para fans y miembros Royal.</p>
            </div>
        </div>

        <div class="admin-editor-grid">
            <div class="admin-panel">
                <div class="admin-form-grid admin-form-grid-wide">
                    <label>
                        <span>Titulo</span>
                        <input id="notifTitle" type="text" value="Reny Renteria publico nuevo contenido">
                    </label>
                    <label class="admin-field-wide">
                        <span>Mensaje</span>
                        <textarea id="notifMsg" rows="4">Ya puedes ver el nuevo contenido desde tu cuenta.</textarea>
                    </label>
                </div>
                <button class="admin-button admin-button-primary" type="button" data-admin-notification-send>Enviar notificacion de prueba</button>
            </div>
            <aside class="admin-panel admin-live-preview">
                <h2>Preview</h2>
                <div class="admin-notification-preview">
                    <strong data-admin-notif-title>Reny Renteria publico nuevo contenido</strong>
                    <span data-admin-notif-message>Ya puedes ver el nuevo contenido desde tu cuenta.</span>
                </div>
            </aside>
        </div>
    </section>

    <section id="sec-equipo" class="admin-dashboard-section" data-admin-section-panel="equipo">
        <x-admin.mock-section title="Equipo y Permisos" copy="Control de admins, editores y moderadores." action="Invitar editor" />
    </section>

    <section id="sec-historial" class="admin-dashboard-section" data-admin-section-panel="historial">
        <x-admin.mock-section title="Historial de Cambios" copy="Auditoria de publicaciones, pagos y acciones del equipo." action="Exportar historial" />
    </section>

    <section id="sec-ajustes" class="admin-dashboard-section" data-admin-section-panel="ajustes">
        <x-admin.mock-section title="Ajustes del Sitio" copy="Configuracion general del CMS y del sitio publico." action="Guardar ajustes" />
    </section>
@endsection
