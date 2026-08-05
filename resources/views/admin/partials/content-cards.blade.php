<div class="admin-filter-bar" data-admin-filter-scope>
    <button type="button" class="is-active" data-admin-filter="todos">Todos</button>
    <button type="button" data-admin-filter="publico">Contenido Libre (Publico)</button>
    <button type="button" data-admin-filter="royal">Solo miembros Royal</button>
    <button type="button" data-admin-filter="borrador">Borradores</button>
</div>

<div class="admin-content-card-grid">
    @forelse ($contents as $content)
        <article class="admin-content-card content-item" data-type="{{ $content['filter'] }}">
            <div class="admin-content-thumb" data-kind="{{ $content['type'] }}">
                <span>{{ strtoupper(mb_substr($content['type'], 0, 1)) }}</span>
            </div>
            <div class="admin-content-body">
                <header>
                    <span @class([
                        'admin-status-pill',
                        'admin-status-royal' => $content['filter'] === 'royal',
                        'admin-status-info' => $content['filter'] === 'publico',
                        'admin-status-warning' => $content['filter'] === 'borrador',
                    ])>
                        {{ $content['filter'] === 'royal' ? 'Solo Royal' : ($content['filter'] === 'borrador' ? 'Borrador' : 'Publico') }}
                    </span>
                    <span @class([
                        'admin-status-pill',
                        'admin-status-success' => $content['status'] === 'published',
                        'admin-status-info' => $content['status'] === 'scheduled',
                        'admin-status-warning' => $content['status'] === 'draft',
                    ])>
                        {{ $content['status'] }}
                    </span>
                </header>
                <h3>{{ $content['title'] }}</h3>
                <p>{{ $content['type'] }} · {{ $content['timestamp'] }}</p>
                @if (! empty($content['summary']))
                    <small>{{ $content['summary'] }}</small>
                @endif
                <footer>
                    <a class="admin-button admin-button-ghost" href="{{ $content['previewUrl'] }}">Ver antes</a>
                    <a class="admin-button admin-button-soft" href="{{ $content['editUrl'] }}">Editar</a>
                    @if (($content['status'] ?? null) === 'draft' && filled($content['deleteUrl'] ?? null))
                        <form method="POST" action="{{ $content['deleteUrl'] }}" onsubmit="return confirm('Delete this draft?')">
                            @csrf
                            @method('DELETE')
                            <button class="admin-button admin-button-danger" type="submit">Eliminar borrador</button>
                        </form>
                    @elseif (($content['status'] ?? null) !== 'archived' && filled($content['archiveUrl'] ?? null) && auth()->user()?->canPublishContent())
                        <form method="POST" action="{{ $content['archiveUrl'] }}" onsubmit="return confirm('Archive this content and remove it from public pages?')">
                            @csrf
                            <button class="admin-button admin-button-danger" type="submit">Archivar</button>
                        </form>
                    @endif
                </footer>
            </div>
        </article>
    @empty
        <div class="admin-empty-state">No content yet.</div>
    @endforelse
</div>
