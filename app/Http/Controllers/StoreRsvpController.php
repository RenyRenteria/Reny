<?php

namespace App\Http\Controllers;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\FanEvent;
use App\Models\Ticket;
use App\Services\PublicCmsContentService;
use App\Services\TicketCodeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreRsvpController extends Controller
{
    /**
     * @var array<string, array{title: string, venue: string, address: string, starts_at: string, timezone: string}>
     */
    private const STATIC_RSVP_EVENTS = [
        'concert' => [
            'title' => 'Reny Renteria en Concierto',
            'venue' => 'Rock & Folk Pty, Ciudad de Panama',
            'address' => 'Rock & Folk Pty, Ciudad de Panama',
            'starts_at' => '2026-09-21 19:30:00',
            'timezone' => 'America/Panama',
        ],
        'making' => [
            'title' => 'Making The Deluxe Album',
            'venue' => 'Royal Stream',
            'address' => 'Royal Stream',
            'starts_at' => '2026-08-31 19:00:00',
            'timezone' => 'America/Panama',
        ],
    ];

    public function __invoke(Request $request, TicketCodeService $ticketCodes): JsonResponse
    {
        $validated = $request->validate([
            'event_key' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'event_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $eventKey = trim((string) $validated['event_key']);

        $result = DB::transaction(function () use ($eventKey, $ticketCodes, $user) {
            $event = $this->resolveEvent($eventKey, $user);
            $ticket = Ticket::query()
                ->where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->whereIn('status', ['reserved', 'confirmed', 'checked_in'])
                ->latest('updated_at')
                ->first();

            if (! $ticket) {
                $issued = $ticketCodes->issue(
                    user: $user,
                    event: $event,
                    holderName: $user->name,
                    status: 'reserved',
                    rsvpStatus: 'confirmed',
                );
                $ticket = $issued['ticket'];
            } elseif ($ticket->rsvp_status !== 'confirmed') {
                $ticket->forceFill([
                    'rsvp_status' => 'confirmed',
                ])->save();
                $ticket = $ticket->fresh('event');
            }

            return [
                'event' => $event,
                'ticket' => $ticket->fresh('event'),
            ];
        });

        PublicCmsContentService::forgetCachedUserPayloads($user);

        /** @var FanEvent $event */
        $event = $result['event'];
        /** @var Ticket $ticket */
        $ticket = $result['ticket'];

        return response()->json([
            'status' => 'confirmed',
            'event' => [
                'key' => $eventKey,
                'name' => $event->title,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'venue' => $event->venue,
            ],
            'ticket' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'rsvp_status' => $ticket->rsvp_status,
                'code' => $ticketCodes->displayCode($ticket),
            ],
            'account_url' => route('account.show'),
            'message' => 'RSVP confirmed.',
        ]);
    }

    private function resolveEvent(string $eventKey, $user): FanEvent
    {
        $source = $this->cmsEvent($eventKey, $user) ?? self::STATIC_RSVP_EVENTS[$eventKey] ?? null;

        if (! $source) {
            throw ValidationException::withMessages([
                'event_key' => 'This RSVP event is not available.',
            ]);
        }

        $timezone = $source['timezone'] ?? 'America/Panama';
        $startsAt = CarbonImmutable::parse($source['starts_at'], $timezone);

        $event = FanEvent::firstOrCreate([
            'title' => $source['title'],
            'starts_at' => $startsAt,
        ], [
            'venue' => $source['venue'],
            'address' => $source['address'],
            'timezone' => $timezone,
            'status' => 'scheduled',
            'metadata' => [
                'source' => 'store_rsvp',
                'store_event_key' => $eventKey,
            ],
        ]);

        if (
            ($event->metadata['source'] ?? null) !== 'store_rsvp'
            || ($event->metadata['store_event_key'] ?? null) !== $eventKey
        ) {
            $event->forceFill([
                'metadata' => [
                    ...($event->metadata ?? []),
                    'source' => 'store_rsvp',
                    'store_event_key' => $eventKey,
                ],
            ])->save();
        }

        return $event->fresh();
    }

    /**
     * @return array{title: string, venue: string, address: string, starts_at: string, timezone: string}|null
     */
    private function cmsEvent(string $eventKey, $user): ?array
    {
        $content = EditorialContent::query()
            ->visibleFor($user)
            ->where('type', ContentType::Event->value)
            ->where(function ($query) use ($eventKey): void {
                $query
                    ->where('purchase_key', $eventKey)
                    ->orWhere('slug', $eventKey);
            })
            ->first();

        if (! $content) {
            return null;
        }

        if (data_get($content->metadata, 'ticketing_mode') !== 'rsvp') {
            throw ValidationException::withMessages([
                'event_key' => 'This event requires checkout instead of RSVP.',
            ]);
        }

        return [
            'title' => $content->title,
            'venue' => (string) data_get($content->metadata, 'location', 'Online'),
            'address' => (string) data_get($content->metadata, 'address', data_get($content->metadata, 'location', 'Online')),
            'starts_at' => (string) data_get($content->metadata, 'starts_at', now()->addMonth()->toDateTimeString()),
            'timezone' => (string) data_get($content->metadata, 'timezone', 'America/Panama'),
        ];
    }
}
