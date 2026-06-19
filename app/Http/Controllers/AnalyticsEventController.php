<?php

namespace App\Http\Controllers;

use App\Models\AccessEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class AnalyticsEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', Rule::in(['page_view', 'permission_denied'])],
            'payload' => ['nullable', 'array', 'max:20'],
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

        return [
            'type' => is_string($itemType) ? $itemType : null,
            'key' => is_string($itemId) ? $itemId : null,
        ];
    }
}
