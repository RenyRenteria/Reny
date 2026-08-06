<?php

namespace App\Http\Controllers;

use App\Models\AccessEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsEventController extends Controller
{
    private const MAX_BODY_BYTES = 2048;

    private const PERSISTED_EVENTS = [
        'page_view',
        'permission_denied',
        'paywall_triggered_from_photo',
        'store_product_opened',
        'store_checkout_started',
        'store_checkout_validation_failed',
        'store_payment_started',
        'store_payment_succeeded',
        'store_payment_failed',
        'store_payment_canceled',
        'store_payment_unavailable',
        'music_play_started',
        'video_play_started',
        'free_event_rsvp_succeeded',
        'store_rsvp_succeeded',
    ];

    public function store(Request $request): JsonResponse
    {
        if (strlen($request->getContent()) > self::MAX_BODY_BYTES) {
            return response()->json(['message' => 'Payload too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $data = $request->validate([
            'name' => ['required', 'string', Rule::in(self::PERSISTED_EVENTS)],
            'schema_version' => ['nullable', 'integer', Rule::in([1])],
            'event_id' => ['nullable', 'uuid'],
            'session_id' => ['nullable', 'uuid'],
            'payload' => ['nullable', 'array:screen,path,result,title,referrer,section,item_type,item_id,item_label,photo_id,album_id,source,method,checkout_state,reason,currency,item_count,rsvp_status,ticket_status', 'max:20'],
            'payload.screen' => ['nullable', 'string', 'max:80'],
            'payload.path' => ['nullable', 'string', 'max:200'],
            'payload.result' => ['nullable', 'string', 'max:40'],
            'payload.title' => ['nullable', 'string', 'max:180'],
            'payload.referrer' => ['nullable', 'string', 'max:300'],
            'payload.section' => ['nullable', 'string', 'max:80'],
            'payload.item_type' => ['nullable', 'string', 'max:80'],
            'payload.item_id' => ['nullable', 'string', 'max:120'],
            'payload.item_label' => ['nullable', 'string', 'max:180'],
            'payload.photo_id' => ['nullable', 'string', 'max:120'],
            'payload.album_id' => ['nullable', 'string', 'max:120'],
            'payload.source' => ['nullable', 'string', 'max:80'],
            'payload.method' => ['nullable', 'string', Rule::in(['paypal', 'card', 'apple_pay', 'local'])],
            'payload.checkout_state' => ['nullable', 'string', 'max:40'],
            'payload.reason' => ['nullable', 'string', 'max:120'],
            'payload.currency' => ['nullable', 'string', 'size:3'],
            'payload.item_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'payload.rsvp_status' => ['nullable', 'string', 'max:40'],
            'payload.ticket_status' => ['nullable', 'string', 'max:40'],
            'timestamp' => ['nullable', 'date'],
        ]);

        $name = $data['name'];
        $payload = $data['payload'] ?? [];
        $resource = $this->resourceFor($name, $payload);

        $sessionKey = isset($data['session_id'])
            ? hash_hmac('sha256', $data['session_id'], (string) config('app.key'))
            : null;
        $idempotencyKey = isset($data['event_id'])
            ? hash_hmac('sha256', $data['event_id'], (string) config('app.key'))
            : null;
        $attributes = [
            'user_id' => null,
            'event_name' => $name,
            'schema_version' => (int) ($data['schema_version'] ?? 1),
            'resource_type' => $resource['type'],
            'resource_key' => $resource['key'],
            'session_key' => $sessionKey,
            'client_occurred_at' => $data['timestamp'] ?? null,
            'metadata' => [
                ...Arr::except($payload, ['referrer']),
                'client_timestamp' => $data['timestamp'] ?? null,
            ],
        ];

        if ($idempotencyKey === null) {
            AccessEvent::create($attributes);

            return response()->json(['ok' => true, 'created' => true], 201);
        }

        $event = AccessEvent::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            $attributes,
        );

        return response()->json([
            'ok' => true,
            'created' => $event->wasRecentlyCreated,
        ], $event->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string|null, key: string|null}
     */
    private function resourceFor(string $name, array $payload): array
    {
        $path = (string) Arr::get($payload, 'path', '');
        $screen = (string) Arr::get($payload, 'screen', '');
        $itemType = Arr::get($payload, 'item_type');
        $itemId = Arr::get($payload, 'item_id');

        if ($name === 'page_view') {
            return [
                'type' => 'page',
                'key' => $path === '/' || $screen === 'music' ? 'home' : ($screen ?: trim($path, '/')),
            ];
        }

        if ($name === 'permission_denied') {
            return [
                'type' => 'access_gate',
                'key' => (string) (Arr::get($payload, 'section') ?: $itemId ?: $screen ?: 'unknown'),
            ];
        }

        if ($name === 'paywall_triggered_from_photo') {
            return [
                'type' => 'photo',
                'key' => (string) ($itemId ?: Arr::get($payload, 'photo_id') ?: 'unknown'),
            ];
        }

        if ($name === 'store_product_opened') {
            return [
                'type' => 'product',
                'key' => (string) ($itemId ?: 'unknown'),
            ];
        }

        if (in_array($name, [
            'store_checkout_started',
            'store_checkout_validation_failed',
            'store_payment_started',
            'store_payment_succeeded',
            'store_payment_failed',
            'store_payment_canceled',
            'store_payment_unavailable',
        ], true)) {
            return [
                'type' => str_starts_with($name, 'store_checkout_') ? 'checkout' : 'payment',
                'key' => (string) ($itemId ?: Arr::get($payload, 'method', 'unknown')),
            ];
        }

        if (in_array($name, ['music_play_started', 'video_play_started'], true)) {
            return [
                'type' => $name === 'music_play_started' ? 'music' : 'video',
                'key' => (string) ($itemId ?: 'unknown'),
            ];
        }

        if (in_array($name, ['free_event_rsvp_succeeded', 'store_rsvp_succeeded'], true)) {
            return [
                'type' => 'show',
                'key' => (string) ($itemId ?: 'unknown'),
            ];
        }

        return [
            'type' => is_string($itemType) ? $itemType : null,
            'key' => is_string($itemId) ? $itemId : null,
        ];
    }
}
