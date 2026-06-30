<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoAssetController extends Controller
{
    public function show(Request $request, Photo $photo): StreamedResponse
    {
        abort_unless($photo->canExposeOptimizedTo($request->user()), 403);
        abort_unless($photo->public_disk && $photo->public_path, 404);

        return Storage::disk($photo->public_disk)->response(
            $photo->public_path,
            basename($photo->public_path),
            ['Cache-Control' => 'private, max-age=300']
        );
    }
}
