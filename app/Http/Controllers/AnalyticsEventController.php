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

    public function store(Request $request): JsonResponse
    {
        if (strlen($request->getContent()) > self::MAX_BODY_BYTES) {
            return response()->json(['message' => 'Payload too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $data = $request->validate([
            'name' => ['required', 'string', Rule::in(['page_view', 'permission_denied', 'paywall_triggered_from_photo'])],
            'payload' => ['nullable', 'array:screen,path,result,title,referrer,section,item_type,item_id,photo_id,album_id,source', 'max:10'],
            'payload.screen' => ['nullable', 'string', 'max:80'],
            'payload.path' => ['nullable', 'string', 'max:200'],
            'payload.result' => ['nullable', 'string', 'max:40'],
            'payload.title' => ['nullable', 'string', 'max:180'],
            'payload.referrer' => ['nullable', 'string', 'max:300'],
            'payload.section' => ['nullable', 'string', 'max:80'],
            'payload.item_type' => ['nullable', 'string', 'max:80'],
            'payload.item_id' => ['nullable', 'string', 'max:120'],
            'payload.photo_id' => ['nullable', 'string', 'max:120'],
            'payload.album_id' => ['nullable', 'string', 'max:120'],
            'payload.source' => ['nullable', 'string', 'max:80'],
            'timestamp' => ['nullable', 'date'],
        ]);

        $name = $data['name'];
        $payload = $data['payload'] ?? [];
        $resource = $this->resourceFor($name, $payload);

        AccessEvent::create([
            'user_id' => $request->user()?->id,
            'event_name' => $name,
            'resource_type' => $resource['type'],
            'resource_key' => $resource['key'],
            'metadata' => [
                ...$payload,
                'client_timestamp' => $data['timestamp'] ?? null,
            ],
        ]);

        return response()->json(['ok' => true], 201);
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

        return [
            'type' => is_string($itemType) ? $itemType : null,
            'key' => is_string($itemId) ? $itemId : null,
        ];
    }
}
