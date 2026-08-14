<?php

namespace App\Http\Controllers;

use App\Models\CommunityCountryClubMessage;
use App\Models\User;
use App\Services\CommunityInteractionService;
use App\Services\PublicCmsContentService;
use App\Support\EntitlementMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CommunityInteractionController extends Controller
{
    public function liveChatMessages(Request $request, CommunityInteractionService $community): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'messages' => $community->liveChatMessages($request->user()),
        ]);
    }

    public function storeLiveChatMessage(Request $request, CommunityInteractionService $community): JsonResponse
    {
        if ($blocked = $this->blockedResponse($request)) {
            return $blocked;
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:300'],
        ]);

        return response()->json([
            'status' => 'created',
            ...$community->createLiveChatMessage($request->user(), trim((string) $validated['body'])),
        ], 201);
    }

    public function blockLiveChatUser(Request $request, CommunityInteractionService $community, User $user): JsonResponse
    {
        if ($blocked = $this->blockedResponse($request)) {
            return $blocked;
        }

        return response()->json([
            'status' => 'ok',
            ...$community->blockLiveChatUser($request->user(), $user),
        ]);
    }

    public function moderateLiveChatMessage(
        Request $request,
        CommunityInteractionService $community,
        CommunityCountryClubMessage $message,
    ): JsonResponse {
        abort_unless($community->canModerate($request->user()), 403);

        return response()->json([
            'status' => 'ok',
            ...$community->moderateLiveChatMessage($message),
        ]);
    }

    public function showClub(Request $request, CommunityInteractionService $community, string $club): View
    {
        return view('community-club', [
            'club' => $community->club($club, $request->user()),
            'canUseCommunityActions' => EntitlementMatrix::canUseRoyalFeature($request->user()),
        ]);
    }

    public function like(Request $request, CommunityInteractionService $community, string $post): JsonResponse
    {
        if ($blocked = $this->blockedResponse($request)) {
            return $blocked;
        }

        return response()->json([
            'status' => 'ok',
            ...$community->toggleLike($request->user(), $post),
        ]);
    }

    public function recordVideoView(
        Request $request,
        CommunityInteractionService $community,
        PublicCmsContentService $cms,
        string $post,
        string $video,
    ): JsonResponse {
        $publicCms = $cms->community($request->user());
        $viewerSessionKey = $request->session()->get('community_video_viewer_key');

        if ($request->user() === null && ! is_string($viewerSessionKey)) {
            $viewerSessionKey = (string) Str::uuid();
            $request->session()->put('community_video_viewer_key', $viewerSessionKey);
        }

        return response()->json([
            'status' => 'ok',
            ...$community->recordVideoView(
                $request->user(),
                is_string($viewerSessionKey) ? $viewerSessionKey : '',
                $post,
                $video,
                $publicCms['posts'] ?? [],
            ),
        ]);
    }

    public function reply(Request $request, CommunityInteractionService $community, string $post): JsonResponse
    {
        if ($blocked = $this->blockedResponse($request)) {
            return $blocked;
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        return response()->json([
            'status' => 'created',
            ...$community->createReply($request->user(), $post, trim((string) $validated['body'])),
        ], 201);
    }

    public function vote(Request $request, CommunityInteractionService $community, string $poll): JsonResponse
    {
        $blocked = str_starts_with($poll, 'cms-poll-')
            ? $this->loginRequiredResponse($request)
            : $this->blockedResponse($request);

        if ($blocked) {
            return $blocked;
        }

        $validated = $request->validate([
            'option_key' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9._-]+$/'],
            'option_label' => ['nullable', 'string', 'max:160'],
        ]);

        $result = $community->recordVote(
            $request->user(),
            $poll,
            (string) $validated['option_key'],
            $validated['option_label'] ?? null,
        );

        if (! $result['accepted']) {
            return response()->json([
                'status' => 'blocked',
                ...$result,
            ], 409);
        }

        return response()->json([
            'status' => 'ok',
            ...$result,
        ]);
    }

    public function storeClub(Request $request, CommunityInteractionService $community): JsonResponse
    {
        if ($blocked = $this->blockedResponse($request)) {
            return $blocked;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'activity' => ['required', 'string', 'min:4', 'max:140'],
        ]);

        return response()->json([
            'status' => 'created',
            ...$community->createClub(
                $request->user(),
                trim((string) $validated['name']),
                trim((string) $validated['activity']),
            ),
        ], 201);
    }

    public function joinClub(Request $request, CommunityInteractionService $community, string $club): JsonResponse
    {
        if ($blocked = $this->blockedResponse($request)) {
            return $blocked;
        }

        return response()->json([
            'status' => 'ok',
            ...$community->joinClub($request->user(), $club),
        ]);
    }

    public function clubMessage(Request $request, CommunityInteractionService $community, string $club): JsonResponse
    {
        if ($blocked = $this->blockedResponse($request)) {
            return $blocked;
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:300'],
        ]);

        return response()->json([
            'status' => 'created',
            ...$community->createClubMessage($request->user(), $club, trim((string) $validated['body'])),
        ], 201);
    }

    private function blockedResponse(Request $request): ?JsonResponse
    {
        if ($blocked = $this->loginRequiredResponse($request)) {
            return $blocked;
        }

        $user = $request->user();

        if (! EntitlementMatrix::canUseRoyalFeature($user)) {
            return response()->json([
                'message' => 'Royal Pass required for community actions.',
                'store_url' => route('store'),
            ], 403);
        }

        return null;
    }

    private function loginRequiredResponse(Request $request): ?JsonResponse
    {
        if ($request->user()) {
            return null;
        }

        return response()->json([
            'message' => 'Sign in to use community actions.',
            'login_url' => route('login'),
        ], 401);
    }
}
