<?php

namespace App\Http\Controllers;

use App\Services\Media\MediaLibraryService;
use App\Services\Media\MuxWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MuxWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MuxWebhookVerifier $verifier,
        MediaLibraryService $library
    ): JsonResponse {
        abort_unless($verifier->verify($request->getContent(), $request->header('Mux-Signature')), 401);

        $asset = $library->applyMuxWebhook($request->json()->all());

        return response()->json([
            'status' => 'accepted',
            'media_asset_id' => $asset?->id,
        ]);
    }
}
