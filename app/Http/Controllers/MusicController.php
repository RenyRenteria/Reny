<?php

namespace App\Http\Controllers;

use App\Models\EditorialContent;
use App\Services\PublicCmsContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MusicController extends Controller
{
    public function index(Request $request, PublicCmsContentService $cms): View
    {
        return view('welcome', [
            'publicCms' => $cms->music($request->user()),
        ]);
    }

    public function albums(Request $request, PublicCmsContentService $cms): View
    {
        return $this->collection($request, $cms, 'albums');
    }

    public function singles(Request $request, PublicCmsContentService $cms): View
    {
        return $this->collection($request, $cms, 'singles');
    }

    public function playlists(Request $request, PublicCmsContentService $cms): View
    {
        return $this->collection($request, $cms, 'playlists');
    }

    public function play(Request $request, PublicCmsContentService $cms, EditorialContent $content): JsonResponse
    {
        $track = $request->query('track');
        $response = $cms->musicPlayback(
            $content,
            $request->user(),
            is_numeric($track) ? max(0, (int) $track) : null,
        );

        return response()->json($response['payload'], $response['status']);
    }

    public function album(Request $request, PublicCmsContentService $cms, EditorialContent $album): View
    {
        return view('music.album-detail', [
            'album' => $cms->albumDetail($album, $request->user()),
        ]);
    }

    private function collection(Request $request, PublicCmsContentService $cms, string $section): View
    {
        return view('music.index', [
            'section' => $section,
            'publicCms' => $cms->musicCollection($request->user(), $section),
        ]);
    }
}
