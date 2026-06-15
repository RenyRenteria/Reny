<?php

namespace App\Http\Controllers;

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

        return response()->json([
            'status' => 'checked_in',
            'ticket_id' => $ticket->id,
            'event' => $ticket->event?->title,
            'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
        ]);
    }
}
