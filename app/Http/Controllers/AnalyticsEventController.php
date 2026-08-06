<?php

namespace App\Http\Controllers;

use App\Models\AccessEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsEventController extends Controller
{
    private const MAX_BODY_BYTES = 3072;

    private const EVENT_NAMES = [
        'page_view',
        'permission_denied',
        'paywall_triggered_from_photo',
        'store_product_opened',
        'store_checkout_started',
        'store_payment_succeeded',
        'store_payment_failed',
        'music_play_started',
        'video_play_started',
        'photo_opened',
        'community_note_opened',
        'free_event_rsvp_succeeded',
        'store_rsvp_succeeded',
        'rsvp_confirmed',
        'ticket_purchased',
        'ticket_checked_in',
    ];

    private const SAFE_METADATA_KEYS = [
        'screen',
        'result',
        'section',
        'item_type',
        'item_id',
        'item_label',
        'source',
        'reason',
        'method',
        'checkout_state',
        'item_count',
        'access_state',
        'currency',
    ];

    private const INPUT_METADATA_KEYS = [
        ...self::SAFE_METADATA_KEYS,
        'path',
        'title',
        'referrer',
        'photo_id',
        'album_id',
        'paypal_order_id',
    ];

    public function store(Request $request): JsonResponse
    {
        if (strlen($request->getContent()) > self::MAX_BODY_BYTES) {
            return response()->json(['message' => 'Payload too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $data = $request->validate([
            'name' => ['required', 'string', Rule::in(self::EVENT_NAMES)],
            'schema_version' => ['nullable', 'integer', 'min:1', 'max:1'],
            'session_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'event_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload' => ['nullable', 'array:'.implode(',', self::INPUT_METADATA_KEYS), 'max:21'],
            'payload.screen' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.path' => ['nullable', 'string', 'max:200'],
            'payload.result' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.title' => ['nullable', 'string', 'max:180'],
            'payload.referrer' => ['nullable', 'string', 'max:300'],
            'payload.section' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.item_type' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.item_id' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.item_label' => ['nullable', 'string', 'max:180'],
            'payload.photo_id' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.album_id' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.source' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.reason' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.method' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.checkout_state' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.item_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'payload.access_state' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload.currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'payload.paypal_order_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'timestamp' => ['nullable', 'date'],
        ]);

        $name = $data['name'];
        $inputPayload = $data['payload'] ?? [];
        $payload = $this->metadataForStorage($name, $inputPayload);
        $resource = $this->resourceFor($name, $inputPayload);
        $sessionId = $data['session_id'] ?? $this->fallbackSessionId($request);
        $idempotencyKey = isset($data['event_id']) ? $this->clientIdempotencyKey($data['event_id']) : null;

        if ($idempotencyKey && AccessEvent::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        try {
            AccessEvent::create([
                'user_id' => null,
                'event_name' => $name,
                'schema_version' => $data['schema_version'] ?? 1,
                'occurred_at' => now(),
                'session_id' => $sessionId,
                'idempotency_key' => $idempotencyKey,
                'resource_type' => $resource['type'],
                'resource_key' => $resource['key'],
                'result' => $payload['result'] ?? null,
                'metadata' => [
                    ...$payload,
                    'client_timestamp' => $data['timestamp'] ?? null,
                ],
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        return response()->json(['ok' => true], Response::HTTP_CREATED);
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
                'key' => $path === '/' || $screen === 'home' ? 'home' : ($screen ?: 'unknown'),
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

        return [
            'type' => is_string($itemType) ? $itemType : $this->defaultResourceType($name),
            'key' => is_string($itemId) ? $itemId : ($screen ?: 'unknown'),
        ];
    }

    private function defaultResourceType(string $name): string
    {
        return match (true) {
            str_starts_with($name, 'music_') => 'music',
            str_starts_with($name, 'video_') => 'video',
            str_starts_with($name, 'photo_') => 'photo',
            str_starts_with($name, 'community_') => 'community',
            str_starts_with($name, 'store_') => 'store',
            str_contains($name, 'rsvp') => 'show',
            default => 'interaction',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadataForStorage(string $name, array $payload): array
    {
        $metadata = Arr::only($payload, self::SAFE_METADATA_KEYS);

        if (! in_array($name, ['music_play_started', 'video_play_started', 'photo_opened'], true)) {
            unset($metadata['item_label']);
        }

        return $metadata;
    }

    private function fallbackSessionId(Request $request): string
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : Str::uuid()->toString();

        return substr(hash('sha256', 'analytics-session:'.$sessionId), 0, 64);
    }

    private function clientIdempotencyKey(string $eventId): string
    {
        return 'client:'.substr(hash('sha256', $eventId), 0, 57);
    }
}
