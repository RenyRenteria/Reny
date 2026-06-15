<?php

namespace App\Services;

use App\Models\FanEvent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketCodeService
{
    private const DISPLAY_CODE_PREFIX = 'TKT';

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
            'holder_name' => $holderName ?: $user->name,
            'status' => $status,
            'rsvp_status' => $rsvpStatus,
            'purchased_at' => now(),
        ]);

        $displayCode = $this->displayCode($ticket);

        $ticket->forceFill([
            'ticket_code_preview' => substr($displayCode, -8),
        ])->save();

        return [
            'ticket' => $ticket->fresh(),
            'code' => $displayCode,
        ];
    }

    public function checkIn(string $code): ?Ticket
    {
        return DB::transaction(function () use ($code) {
            $ticket = $this->findTicket($code);

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

    public function displayCode(Ticket $ticket): string
    {
        return self::DISPLAY_CODE_PREFIX.'-'.$ticket->id.'-'.$this->signature($ticket);
    }

    private function findTicket(string $code): ?Ticket
    {
        $ticket = $this->findByDisplayCode($code);

        if ($ticket) {
            return $ticket;
        }

        return Ticket::query()
            ->with('event')
            ->where('ticket_code_hash', $this->hash($code))
            ->first();
    }

    private function findByDisplayCode(string $code): ?Ticket
    {
        if (! preg_match('/^'.self::DISPLAY_CODE_PREFIX.'-(\d+)-([A-F0-9]{24})$/', strtoupper($code), $matches)) {
            return null;
        }

        $ticket = Ticket::query()
            ->with('event')
            ->find((int) $matches[1]);

        if (! $ticket || ! hash_equals($this->signature($ticket), $matches[2])) {
            return null;
        }

        return $ticket;
    }

    private function signature(Ticket $ticket): string
    {
        return strtoupper(substr(hash_hmac(
            'sha256',
            implode(':', ['ticket', $ticket->id, $ticket->ticket_code_hash]),
            config('app.key')
        ), 0, 24));
    }
}
