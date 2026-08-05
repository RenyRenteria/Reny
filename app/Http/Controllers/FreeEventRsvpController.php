<?php

namespace App\Http\Controllers;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\Rsvp;
use App\Services\StorefrontSettingsService;
use App\Support\CountryOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FreeEventRsvpController extends Controller
{
    public function __invoke(Request $request, StorefrontSettingsService $storefront): JsonResponse
    {
        $validated = $request->validate([
            'event_key' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'event_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'country' => ['required', 'string', Rule::in(CountryOptions::names())],
        ]);

        $event = $this->resolveFreeEvent(
            $storefront,
            (string) $validated['event_key'],
            (string) $validated['event_name'],
        );
        $email = str((string) $validated['email'])->lower()->toString();

        $existing = Rsvp::query()
            ->where('event_key', $event['event_key'])
            ->where('email', $email)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'already_registered',
                'message' => 'Ya estás registrado. Te esperamos!',
                'event' => [
                    'key' => $event['event_key'],
                    'name' => $event['event_name'],
                ],
            ]);
        }

        $rsvp = Rsvp::create([
            'event_key' => $event['event_key'],
            'event_name' => $event['event_name'],
            'name' => trim((string) $validated['name']),
            'email' => $email,
            'country' => (string) $validated['country'],
            'metadata' => [
                'source' => 'free_event_lead_capture',
                'price_label' => $event['price_label'],
            ],
        ]);

        return response()->json([
            'status' => 'registered',
            'message' => 'Te has registrado con éxito! Te esperamos.',
            'event' => [
                'key' => $rsvp->event_key,
                'name' => $rsvp->event_name,
            ],
        ], 201);
    }

    /**
     * @return array{event_key: string, event_name: string, price_label: string}
     */
    private function resolveFreeEvent(StorefrontSettingsService $storefront, string $eventKey, string $eventName): array
    {
        $event = $this->freeEventSlots($storefront)
            ->first(function (array $slot) use ($eventKey, $eventName): bool {
                $slotKey = (string) ($slot['product_key'] ?? $slot['key'] ?? '');
                $slotName = (string) ($slot['title'] ?? '');

                return $slotKey === $eventKey || strcasecmp($slotName, $eventName) === 0;
            });

        if (! $event) {
            $content = EditorialContent::query()
                ->visibleFor(null)
                ->where('type', ContentType::Event->value)
                ->where(function ($query) use ($eventKey, $eventName): void {
                    $query->where('purchase_key', $eventKey)
                        ->orWhere('slug', $eventKey)
                        ->orWhere('title', $eventName);
                })
                ->first();

            if ($content && data_get($content->metadata, 'ticketing_mode') === 'rsvp') {
                $event = [
                    'product_key' => $content->purchase_key ?: $content->slug,
                    'title' => $content->title,
                    'price_label' => 'FREE',
                ];
            }
        }

        if (! $event || ! $this->isFreePrice((string) ($event['price_label'] ?? ''))) {
            throw ValidationException::withMessages([
                'event_key' => 'This event is not open for free registration.',
            ]);
        }

        return [
            'event_key' => (string) ($event['product_key'] ?? $event['key'] ?? $eventKey),
            'event_name' => (string) ($event['title'] ?? $eventName),
            'price_label' => (string) ($event['price_label'] ?? ''),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function freeEventSlots(StorefrontSettingsService $storefront): Collection
    {
        return collect(data_get($storefront->publicPayload(), 'slots', []))
            ->filter(fn (mixed $slot): bool => is_array($slot) && ($slot['kind'] ?? null) === 'event')
            ->values();
    }

    private function isFreePrice(string $priceLabel): bool
    {
        if (preg_match('/(^|[^a-z])free([^a-z]|$)/i', $priceLabel) === 1) {
            return true;
        }

        $numeric = preg_replace('/[^0-9.]/', '', $priceLabel);

        return in_array($numeric, ['0', '0.0', '0.00'], true);
    }
}
