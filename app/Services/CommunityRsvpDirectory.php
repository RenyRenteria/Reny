<?php

namespace App\Services;

use App\Models\Rsvp;
use App\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CommunityRsvpDirectory
{
    /**
     * @return array{events: Collection<int, array<string, mixed>>, selected_event_key: string, registrations: Collection<int, array<string, mixed>>}
     */
    public function data(?string $selectedEventKey = null): array
    {
        $directory = $this->directory();
        $events = $directory
            ->map(fn (array $event): array => [
                'event_key' => $event['event_key'],
                'event_name' => $event['event_name'],
                'total' => $event['registrations']->sum('tickets'),
                'latest_at' => $event['latest_at'],
            ])
            ->sortByDesc('latest_at')
            ->values();
        $selectedEventKey = trim((string) $selectedEventKey);

        if ($selectedEventKey === '' || ! $directory->has($selectedEventKey)) {
            $selectedEventKey = (string) ($events->first()['event_key'] ?? '');
        }

        return [
            'events' => $events,
            'selected_event_key' => $selectedEventKey,
            'registrations' => $selectedEventKey === ''
                ? collect()
                : $directory->get($selectedEventKey)['registrations']
                    ->sortByDesc('latest_at')
                    ->values(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function event(string $eventKey): ?array
    {
        return $this->directory()->get(trim($eventKey));
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function directory(): Collection
    {
        $directory = collect();

        Rsvp::query()
            ->oldest('created_at')
            ->get()
            ->each(function (Rsvp $rsvp) use ($directory): void {
                $email = $this->publicEmail($rsvp->email);
                $registrationKey = $email !== '' ? 'email:'.mb_strtolower($email) : 'lead:'.$rsvp->id;
                $quantity = max(1, (int) data_get($rsvp->metadata, 'ticket_quantity', 1));

                $this->mergeRegistration(
                    $directory,
                    (string) $rsvp->event_key,
                    (string) $rsvp->event_name,
                    $registrationKey,
                    [
                        'name' => (string) $rsvp->name,
                        'email' => $email,
                        'lead_tickets' => $quantity,
                        'issued_tickets' => 0,
                        'latest_at' => $rsvp->created_at,
                    ],
                );
            });

        Ticket::query()
            ->with(['event', 'user:id,name,email'])
            ->whereIn('status', ['reserved', 'confirmed', 'checked_in'])
            ->where('rsvp_status', 'confirmed')
            ->oldest('created_at')
            ->get()
            ->each(function (Ticket $ticket) use ($directory): void {
                if (! $ticket->event) {
                    return;
                }

                $eventKey = trim((string) data_get($ticket->event->metadata, 'store_event_key'));
                $eventKey = $eventKey !== '' ? $eventKey : 'event-'.$ticket->event->id;
                $email = $this->publicEmail($ticket->user?->email);
                $registrationKey = $email !== ''
                    ? 'email:'.mb_strtolower($email)
                    : 'user:'.($ticket->user_id ?: $ticket->id);

                $this->mergeRegistration(
                    $directory,
                    $eventKey,
                    (string) $ticket->event->title,
                    $registrationKey,
                    [
                        'name' => (string) ($ticket->holder_name ?: $ticket->user?->name ?: 'Guest'),
                        'email' => $email,
                        'lead_tickets' => 0,
                        'issued_tickets' => 1,
                        'latest_at' => $ticket->created_at,
                    ],
                );
            });

        return $directory->map(function (array $event): array {
            $event['registrations'] = $event['registrations']->map(function (array $registration): array {
                $registration['tickets'] = max(
                    1,
                    (int) $registration['lead_tickets'],
                    (int) $registration['issued_tickets'],
                );
                unset($registration['lead_tickets'], $registration['issued_tickets']);

                return $registration;
            });

            return $event;
        });
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $directory
     * @param  array{name: string, email: string, lead_tickets: int, issued_tickets: int, latest_at: CarbonInterface|null}  $incoming
     */
    private function mergeRegistration(
        Collection $directory,
        string $eventKey,
        string $eventName,
        string $registrationKey,
        array $incoming,
    ): void {
        $event = $directory->get($eventKey, [
            'event_key' => $eventKey,
            'event_name' => $eventName,
            'latest_at' => $incoming['latest_at'],
            'registrations' => collect(),
        ]);
        $existing = $event['registrations']->get($registrationKey, [
            'name' => $incoming['name'],
            'email' => $incoming['email'],
            'lead_tickets' => 0,
            'issued_tickets' => 0,
            'latest_at' => $incoming['latest_at'],
        ]);
        $existing['name'] = $incoming['name'] !== '' ? $incoming['name'] : $existing['name'];
        $existing['email'] = $incoming['email'] !== '' ? $incoming['email'] : $existing['email'];
        $existing['lead_tickets'] = max((int) $existing['lead_tickets'], (int) $incoming['lead_tickets']);
        $existing['issued_tickets'] += (int) $incoming['issued_tickets'];
        $existing['latest_at'] = $this->latest($existing['latest_at'], $incoming['latest_at']);
        $event['latest_at'] = $this->latest($event['latest_at'], $incoming['latest_at']);
        $event['registrations']->put($registrationKey, $existing);
        $directory->put($eventKey, $event);
    }

    private function latest(?CarbonInterface $first, ?CarbonInterface $second): ?CarbonInterface
    {
        if (! $first) {
            return $second;
        }

        if (! $second) {
            return $first;
        }

        return $second->greaterThan($first) ? $second : $first;
    }

    private function publicEmail(?string $email): string
    {
        $email = trim((string) $email);

        return str_ends_with(mb_strtolower($email), '@renyrenteria.local') ? '' : $email;
    }
}
