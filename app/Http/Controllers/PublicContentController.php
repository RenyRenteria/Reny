<?php

namespace App\Http\Controllers;

use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Services\PublicCmsContentService;
use App\Services\TicketCodeService;
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

    public function store(Request $request, PublicCmsContentService $cms, TicketCodeService $ticketCodes): View
    {
        $publicCms = $cms->store($request->user());

        return view('store', [
            'publicCms' => $publicCms,
            'rsvpTickets' => $this->rsvpTickets($request, $ticketCodes),
        ]);
    }

    /**
     * @return array<string, array{status: string, rsvp_status: string, code: string, account_url: string}>
     */
    private function rsvpTickets(Request $request, TicketCodeService $ticketCodes): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        return $user->tickets()
            ->with('event')
            ->whereIn('status', ['reserved', 'confirmed', 'checked_in'])
            ->get()
            ->filter(fn ($ticket): bool => ($ticket->event?->metadata['source'] ?? null) === 'store_rsvp'
                && filled($ticket->event?->metadata['store_event_key'] ?? null))
            ->mapWithKeys(fn ($ticket): array => [
                $ticket->event->metadata['store_event_key'] => [
                    'status' => $ticket->status,
                    'rsvp_status' => $ticket->rsvp_status,
                    'code' => $ticketCodes->displayCode($ticket),
                    'account_url' => route('account.show'),
                ],
            ])
            ->all();
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
