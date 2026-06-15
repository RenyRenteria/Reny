<?php

namespace App\Services;

use App\Models\FanEvent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketCodeService
{
    /**
     * @return array{ticket: Ticket, code: string}
     */
    public function issue(
        User $user,
        FanEvent $event,
        ?string $holderName = null,
        string $status = 'confirmed',
        string $rsvpStatus = 'confirmed',
    ): array {
        do {
            $code = Str::random(40);
            $hash = $this->hash($code);
        } while (Ticket::query()->where('ticket_code_hash', $hash)->exists());

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'ticket_code_hash' => $hash,
            'ticket_code_preview' => substr($code, -8),
            'holder_name' => $holderName ?: $user->name,
            'status' => $status,
            'rsvp_status' => $rsvpStatus,
            'purchased_at' => now(),
        ]);

        return [
            'ticket' => $ticket,
            'code' => $code,
        ];
    }

    public function checkIn(string $code): ?Ticket
    {
        return DB::transaction(function () use ($code) {
            $ticket = Ticket::query()
                ->with('event')
                ->where('ticket_code_hash', $this->hash($code))
                ->first();

            if (! $ticket || ! in_array($ticket->status, ['reserved', 'confirmed', 'checked_in'], true)) {
                return null;
            }

            if ($ticket->status === 'checked_in') {
                return $ticket;
            }

            $ticket->forceFill([
                'status' => 'checked_in',
                'checked_in_at' => now(),
            ])->save();

            return $ticket->fresh('event');
        });
    }

    public function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }
}
