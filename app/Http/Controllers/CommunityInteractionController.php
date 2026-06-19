<?php

namespace App\Http\Controllers;

use App\Services\CommunityInteractionService;
use App\Support\EntitlementMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityInteractionController extends Controller
{
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
        if ($blocked = $this->blockedResponse($request)) {
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
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Sign in to use community actions.',
                'login_url' => route('login'),
            ], 401);
        }

        if (! EntitlementMatrix::canUseRoyalFeature($user)) {
            return response()->json([
                'message' => 'Royal Pass required for community actions.',
                'store_url' => route('store'),
            ], 403);
        }

        return null;
    }
}
