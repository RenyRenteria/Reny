<?php

namespace App\Http\Controllers;

use App\Models\EditorialContent;
use App\Services\PublicCmsContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MusicController extends Controller
{
    public function albums(Request $request, PublicCmsContentService $cms): View
    {
        return $this->collection($request, $cms, 'albums');
    }

    public function singles(Request $request, PublicCmsContentService $cms): View
    {
        return $this->collection($request, $cms, 'singles');
    }

    public function play(Request $request, PublicCmsContentService $cms, EditorialContent $content): JsonResponse
    {
        $response = $cms->musicPlayback($content, $request->user());

        return response()->json($response['payload'], $response['status']);
    }

    private function collection(Request $request, PublicCmsContentService $cms, string $section): View
    {
        return view('music.index', [
            'section' => $section,
            'publicCms' => $cms->musicCollection($request->user(), $section),
        ]);
    }
}
