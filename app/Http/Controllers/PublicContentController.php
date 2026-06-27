<?php

namespace App\Http\Controllers;

use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Services\Commerce\ProductCatalog;
use App\Services\CommunityInteractionService;
use App\Services\PublicCmsContentService;
use App\Services\TicketCodeService;
use App\Support\AccountStateView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function home(Request $request, PublicCmsContentService $cms, TicketCodeService $ticketCodes): View
    {
        return view('home', [
            'publicCms' => $cms->home($request->user()),
            'rsvpTickets' => $this->rsvpTickets($request, $ticketCodes),
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

    public function community(
        Request $request,
        PublicCmsContentService $cms,
        CommunityInteractionService $community,
    ): View {
        $publicCms = $cms->community($request->user());

        return view('community', [
            'publicCms' => $publicCms,
            'community' => $community->viewModel($request->user(), $publicCms),
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

    public function checkout(
        Request $request,
        PublicCmsContentService $cms,
        ProductCatalog $products,
        string $product,
    ): View {
        $catalogProduct = $products->find($product, $request->user());

        abort_unless($catalogProduct, 404);

        $publicCms = $cms->store($request->user());

        return view('store-checkout', [
            'publicCms' => $publicCms,
            'checkoutProduct' => $this->checkoutProductPayload($catalogProduct, $publicCms['storefront'] ?? [], $product),
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

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $storefront
     * @return array<string, mixed>
     */
    private function checkoutProductPayload(array $product, array $storefront, string $requestedKey): array
    {
        $key = (string) ($product['key'] ?? $requestedKey);
        $slot = $this->storefrontSlotForProduct($storefront, $key);
        $kind = (string) ($product['kind'] ?? ($slot['kind'] ?? 'product'));
        $priceLabel = filled($slot['price_label'] ?? null) && ($slot['action_type'] ?? null) !== 'rsvp'
            ? (string) $slot['price_label']
            : $this->moneyLabel((int) ($product['amount_cents'] ?? 0), (string) ($product['currency'] ?? 'USD'), $kind);
        $summary = filled($slot['description'] ?? null)
            ? str_replace("\n", ' - ', (string) $slot['description'])
            : $this->checkoutProductSummary($product);
        $typeLabel = $this->checkoutProductType($kind);

        $details = [
            ['label' => 'Price', 'value' => $priceLabel],
            ['label' => 'Type', 'value' => $typeLabel],
            ['label' => 'Checkout', 'value' => 'PayPal in USD'],
            ['label' => 'Royal Pass', 'value' => '1 month included'],
        ];

        if ($eventDate = $this->checkoutEventDate($product['event'] ?? null)) {
            $details[] = ['label' => 'Date', 'value' => $eventDate];
        }

        if (filled(data_get($product, 'event.venue'))) {
            $details[] = ['label' => 'Venue', 'value' => (string) data_get($product, 'event.venue')];
        }

        return [
            'key' => $key,
            'title' => (string) ($slot['title'] ?? $product['title'] ?? str($key)->headline()),
            'eyebrow' => (string) (($slot['eyebrow'] ?? null) ?: $typeLabel),
            'summary' => $summary,
            'price_label' => $priceLabel,
            'amount' => ((int) ($product['amount_cents'] ?? 0)) / 100,
            'currency' => (string) ($product['currency'] ?? 'USD'),
            'type_label' => $typeLabel,
            'image_url' => $this->checkoutProductImage($slot, $product, $kind),
            'image_alt' => (string) ($slot['image_alt'] ?? $product['image_alt'] ?? $slot['title'] ?? $product['title'] ?? 'Store product'),
            'cta_label' => (string) ($slot['cta_label'] ?? ($kind === 'ticket' ? 'GET TICKETS' : 'CONTINUE TO CHECKOUT')),
            'details' => $details,
            'bullets' => $this->checkoutProductBullets($kind),
        ];
    }

    /**
     * @param  array<string, mixed>  $storefront
     * @return array<string, mixed>|null
     */
    private function storefrontSlotForProduct(array $storefront, string $productKey): ?array
    {
        $slot = collect(data_get($storefront, 'slots', []))
            ->filter(fn (mixed $slot): bool => is_array($slot))
            ->first(fn (array $slot): bool => (string) ($slot['product_key'] ?? $slot['key'] ?? '') === $productKey);

        if (is_array($slot)) {
            return $slot;
        }

        $royalPass = data_get($storefront, 'royal_pass', []);

        if ((string) data_get($royalPass, 'product_key', 'royal') !== $productKey) {
            return null;
        }

        return [
            'kind' => 'subscription',
            'title' => data_get($royalPass, 'emphasis', 'Royal Pass'),
            'eyebrow' => 'Membership',
            'description' => trim(data_get($royalPass, 'copy_before', 'Get your').' '.data_get($royalPass, 'emphasis', 'Royal Pass').' '.data_get($royalPass, 'copy_after', 'to unlock exclusive content, community and more')),
            'price_label' => '$4.99/mo',
            'cta_label' => data_get($royalPass, 'cta_label', 'Get Your Royal Pass'),
            'image' => 'images/store/crown-collection.png',
            'image_alt' => 'Royal Pass membership',
        ];
    }

    private function moneyLabel(int $amountCents, string $currency, string $kind): string
    {
        $amount = $amountCents / 100;
        $symbol = strtoupper($currency) === 'USD' ? '$' : strtoupper($currency).' ';
        $formatted = number_format($amount, fmod($amount, 1.0) === 0.0 ? 0 : 2);

        return $symbol.$formatted.($kind === 'subscription' ? '/mo' : '');
    }

    /**
     * @param  array<string, mixed>|null  $slot
     * @param  array<string, mixed>  $product
     */
    private function checkoutProductImage(?array $slot, array $product, string $kind): string
    {
        if (filled($slot['image_url'] ?? null)) {
            return (string) $slot['image_url'];
        }

        if (filled($product['image_url'] ?? null)) {
            return (string) $product['image_url'];
        }

        if (filled($slot['image'] ?? null)) {
            return asset((string) $slot['image']);
        }

        return asset(match ($kind) {
            'ticket' => 'images/store/rosa-dorada.png',
            'subscription', 'merch' => 'images/store/crown-collection.png',
            default => 'images/store/work-in-progress.png',
        });
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function checkoutProductSummary(array $product): string
    {
        return match ((string) ($product['kind'] ?? 'product')) {
            'ticket' => 'Ticket checkout includes account delivery, receipt confirmation and one month of Royal Pass access.',
            'subscription' => 'Royal Pass unlocks exclusive content, community access and member updates for this account.',
            'merch' => 'Limited merch checkout with receipt confirmation and Royal Pass activation after payment.',
            'art_drop' => 'Collector drop checkout with account confirmation and Royal Pass activation after payment.',
            default => 'Store checkout with receipt confirmation and Royal Pass activation after payment.',
        };
    }

    private function checkoutProductType(string $kind): string
    {
        return match ($kind) {
            'ticket' => 'Event',
            'subscription' => 'Membership',
            'art_drop' => 'Collector drop',
            'digital' => 'Digital release',
            default => str($kind)->headline()->toString(),
        };
    }

    /**
     * @param  array<string, mixed>|null  $event
     */
    private function checkoutEventDate(?array $event): ?string
    {
        if (! filled($event['starts_at'] ?? null)) {
            return null;
        }

        try {
            return Carbon::parse((string) $event['starts_at'], (string) ($event['timezone'] ?? config('app.timezone')))
                ->format('M j, Y - g:i A');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function checkoutProductBullets(string $kind): array
    {
        return match ($kind) {
            'ticket' => [
                'Ticket purchase is saved to the account used at checkout.',
                'Name, email, international phone and country are captured before PayPal approval.',
                'Completed purchase activates Royal Pass for 1 month.',
            ],
            'subscription' => [
                'Unlock exclusive content and community access.',
                'Name, email, international phone and country are captured before PayPal approval.',
                'Access starts after PayPal confirms the purchase.',
            ],
            default => [
                'Product access is linked to the checkout account.',
                'Name, email, international phone and country are captured before PayPal approval.',
                'Completed purchase activates Royal Pass for 1 month.',
            ],
        };
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
