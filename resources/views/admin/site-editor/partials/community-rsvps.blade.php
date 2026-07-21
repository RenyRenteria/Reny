<section class="admin-panel community-rsvp-panel" aria-labelledby="community-rsvp-title">
    <div class="admin-section-head">
        <div>
            <p class="admin-kicker">RSVP</p>
            <h2 id="community-rsvp-title">Registros de eventos gratis</h2>
            <p class="admin-panel-copy">Leads capturados desde Get Tickets cuando el precio visible es Free.</p>
        </div>
        @if ($communityRsvps['export_url'])
            <a class="admin-button admin-button-primary" href="{{ $communityRsvps['export_url'] }}">Descargar CSV</a>
        @endif
    </div>

    @if ($communityRsvps['events']->isNotEmpty())
        <form class="community-rsvp-filter" method="GET" action="{{ route('admin.site-editor.show', ['page' => 'community']) }}">
            <label>
                <span>Evento</span>
                <select name="rsvp_event" onchange="this.form.submit()">
                    @foreach ($communityRsvps['events'] as $event)
                        <option value="{{ $event->event_key }}" @selected($communityRsvps['selected_event_key'] === $event->event_key)>
                            {{ $event->event_name }} ({{ $event->total }})
                        </option>
                    @endforeach
                </select>
            </label>
        </form>

        <div class="community-rsvp-table-wrap">
            <table class="community-rsvp-table">
                <thead><tr><th>Nombre</th><th>Correo electrónico</th><th>País</th></tr></thead>
                <tbody>
                    @forelse ($communityRsvps['registrations'] as $rsvp)
                        <tr><td>{{ $rsvp->name }}</td><td>{{ $rsvp->email }}</td><td>{{ $rsvp->country }}</td></tr>
                    @empty
                        <tr><td colspan="3">Este evento todavía no tiene registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="admin-empty-state">Todavía no hay registros RSVP de eventos gratis.</div>
    @endif
</section>
