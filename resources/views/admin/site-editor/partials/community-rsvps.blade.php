<div class="admin-page-heading">
    <div>
        <p class="admin-kicker">Community / RSVP List</p>
        <h1>RSVP List</h1>
        <p>Selecciona un concierto para ver sus asistentes y cantidad de tickets.</p>
    </div>
    @if ($communityRsvps['export_url'])
        <a class="admin-button admin-button-primary" href="{{ $communityRsvps['export_url'] }}">Export CSV</a>
    @endif
</div>

<section class="admin-panel community-rsvp-panel" aria-labelledby="community-rsvp-title">
    <div class="admin-section-head">
        <div>
            <h2 id="community-rsvp-title">Concert registrations</h2>
            <p class="admin-panel-copy">Incluye registros RSVP y tickets emitidos desde la cuenta.</p>
        </div>
    </div>

    @if ($communityRsvps['events']->isNotEmpty())
        <form class="community-rsvp-filter" method="GET" action="{{ route('admin.site-editor.show', ['page' => 'community']) }}">
            <input name="community_section" type="hidden" value="rsvp">
            <label>
                <span>Concert</span>
                <select name="rsvp_event" onchange="this.form.submit()">
                    @foreach ($communityRsvps['events'] as $event)
                        <option value="{{ $event['event_key'] }}" @selected($communityRsvps['selected_event_key'] === $event['event_key'])>
                            {{ $event['event_name'] }} ({{ $event['total'] }})
                        </option>
                    @endforeach
                </select>
            </label>
        </form>

        <div class="community-rsvp-table-wrap">
            <table class="community-rsvp-table">
                <thead><tr><th>Name</th><th>Email</th><th>Tickets</th></tr></thead>
                <tbody>
                    @forelse ($communityRsvps['registrations'] as $registration)
                        <tr>
                            <td>{{ $registration['name'] }}</td>
                            <td>{{ $registration['email'] !== '' ? $registration['email'] : '—' }}</td>
                            <td><strong>{{ $registration['tickets'] }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="3">This concert does not have registrations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="admin-empty-state">No RSVP registrations or concert tickets yet.</div>
    @endif
</section>
