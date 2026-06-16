<?php

namespace App\Http\Controllers;

use App\Enums\EditorialStatus;
use App\Models\EditorialContent;
use App\Services\PublicCmsContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PublicContentController extends Controller
{
    public function home(Request $request, PublicCmsContentService $cms): View
    {
        return view('welcome', [
            'publicCms' => $cms->music($request->user()),
        ]);
    }

    public function videos(Request $request, PublicCmsContentService $cms): View
    {
        return view('videos', [
            'publicCms' => $cms->videos($request->user()),
        ]);
    }

    public function photos(Request $request, PublicCmsContentService $cms): View
    {
        return view('photos', [
            'publicCms' => $cms->photos($request->user()),
        ]);
    }

    public function community(Request $request, PublicCmsContentService $cms): View
    {
        return view('community', [
            'publicCms' => $cms->community($request->user()),
        ]);
    }

    public function store(Request $request, PublicCmsContentService $cms): View
    {
        return view('store', [
            'publicCms' => $cms->store($request->user()),
        ]);
    }

    public function payload(Request $request, PublicCmsContentService $cms, string $page): JsonResponse
    {
        return response()->json($cms->payload($page, $request->user()));
    }

    public function show(Request $request, EditorialContent $content): View|RedirectResponse|Response
    {
        $content->load(['mediaAssets', 'releaseWindows']);

        if ($content->status === EditorialStatus::Archived) {
            return response()->view('public.content-unavailable', [
                'content' => $content,
            ]);
        }

        if (! $content->isVisibleTo($request->user())) {
            if (! $request->user()) {
                return redirect()->route('login');
            }

            abort(403, 'This content is not available for your access level.');
        }

        return view('public.content', [
            'content' => $content,
        ]);
    }
}
