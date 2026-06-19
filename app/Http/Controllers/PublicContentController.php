<?php

namespace App\Http\Controllers;

use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Services\PublicCmsContentService;
use App\Support\AccountStateView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PublicContentController extends Controller
{
    private const VIDEO_CATEGORIES = [
        'music_videos',
        'series',
        'performances',
        'behind_the_scenes',
        'vlogs',
    ];

    public function home(Request $request, PublicCmsContentService $cms): View
    {
        return view('welcome', [
            'publicCms' => $cms->music($request->user()),
        ]);
    }

    public function videos(Request $request, PublicCmsContentService $cms): View
    {
        $category = str((string) $request->query('category'))->lower()->slug('_')->toString();

        return view('videos', [
            'publicCms' => $cms->videos($request->user()),
            'selectedVideoCategory' => in_array($category, self::VIDEO_CATEGORIES, true) ? $category : null,
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

            return response()->view('auth.permission-denied', [
                'message' => $content->visibility === VisibilityAudience::Purchased
                    ? 'This item requires a completed purchase before the full content can render.'
                    : 'This item checks access before it renders protected content.',
                'section' => $content->visibility->value,
                'state' => AccountStateView::for($request->user()),
                'title' => $content->visibility === VisibilityAudience::Purchased
                    ? 'Purchase required'
                    : 'Royal Pass required',
            ], 403);
        }

        return view('public.content', [
            'content' => $content,
        ]);
    }
}
