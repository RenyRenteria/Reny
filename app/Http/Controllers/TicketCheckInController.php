<?php

namespace App\Http\Controllers;

use App\Models\AccessEvent;
use App\Services\TicketCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketCheckInController extends Controller
{
    public function store(Request $request, TicketCodeService $tickets): JsonResponse
    {
        abort_unless($request->user()?->isStaff(), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $ticket = $tickets->checkIn($validated['code']);

        abort_unless($ticket, 404, 'Ticket not valid for check-in.');

        AccessEvent::query()->firstOrCreate([
            'idempotency_key' => 'ticket-check-in:'.$ticket->id,
        ], [
            'event_name' => 'ticket_checked_in',
            'schema_version' => 1,
            'occurred_at' => $ticket->checked_in_at ?? now(),
            'session_id' => substr(hash('sha256', 'staff-session:'.$request->session()->getId()), 0, 64),
            'resource_type' => 'show',
            'resource_key' => (string) $ticket->event_id,
            'result' => 'succeeded',
            'metadata' => [
                'item_type' => 'show',
                'item_id' => (string) $ticket->event_id,
                'result' => 'succeeded',
            ],
        ]);

        return response()->json([
            'status' => 'checked_in',
            'ticket_id' => $ticket->id,
            'event' => $ticket->event?->title,
            'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
        ]);
    }
}
