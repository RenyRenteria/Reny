<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Http;

class MuxVideoService
{
    public function createDirectUpload(string $passthrough): array
    {
        $tokenId = config('services.mux.token_id');
        $tokenSecret = config('services.mux.token_secret');

        if (blank($tokenId) || blank($tokenSecret)) {
            throw new MediaUploadException('Mux credentials are not configured.');
        }

        $response = Http::withBasicAuth($tokenId, $tokenSecret)
            ->acceptJson()
            ->asJson()
            ->post(rtrim((string) config('services.mux.base_url'), '/').'/video/v1/uploads', [
                'cors_origin' => config('services.mux.cors_origin') ?: config('app.url'),
                'new_asset_settings' => [
                    'playback_policies' => [config('services.mux.playback_policy', 'public')],
                    'video_quality' => config('services.mux.video_quality', 'basic'),
                    'passthrough' => $passthrough,
                ],
            ]);

        if ($response->failed()) {
            throw new MediaUploadException('Mux could not create a direct upload URL.');
        }

        $data = $response->json('data');

        if (! is_array($data) || blank($data['id'] ?? null) || blank($data['url'] ?? null)) {
            throw new MediaUploadException('Mux returned an invalid direct upload response.');
        }

        return $data;
    }
}
