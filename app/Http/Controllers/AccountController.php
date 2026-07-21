<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Rsvp;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Commerce\ProductCatalog;
use App\Services\PayPalService;
use App\Services\PointLedgerService;
use App\Services\PublicCmsContentService;
use App\Services\StorefrontSettingsService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AccountController extends Controller
{
    /**
     * @var array<string, bool>
     */
    private array $tableCache = [];

    /**
     * @var array<string, bool>
     */
    private array $columnCache = [];

    public function show(
        Request $request,
        PointLedgerService $points,
        ProductCatalog $products,
        StorefrontSettingsService $storefront,
    ): View {
        $user = $request->user();
        $billingProfileAvailable = $this->accountData('billing_profile', function () use ($user): bool {
            $this->loadBillingProfile($user);

            return true;
        }, false);

        if (! $user->relationLoaded('billingProfile')) {
            $user->setRelation('billingProfile', null);
        }

        $storefrontPayload = $this->accountData(
            'storefront',
            fn (): array => $storefront->publicPayload(),
            ['slots' => []],
        );
        $registeredEvents = $this->accountData(
            'registered_events',
            fn (): Collection => $this->registeredEventCards($user, $storefrontPayload, $products),
            collect(),
        );
        $billingSummary = $billingProfileAvailable
            ? $this->accountData(
                'billing_summary',
                fn (): array => $this->billingSummary($user, $products),
                $this->fallbackBillingSummary($user, true),
            )
            : $this->fallbackBillingSummary($user, false);

        return view('account.show', [
            'availableEvents' => $this->accountData(
                'available_events',
                fn (): Collection => $this->availableEventCards($user, $storefrontPayload, $products, $registeredEvents),
                collect(),
            ),
            'billingProfile' => $user->billingProfile,
            'billingSummary' => $billingSummary,
            'currencies' => config('user_hub.currencies', []),
            'initials' => $this->initials($user->name),
            'languages' => config('user_hub.languages', []),
            'pointBalance' => $this->accountData(
                'point_balance',
                fn (): int => $this->pointBalance($user, $points),
                0,
            ),
            'purchases' => $this->accountData(
                'purchases',
                fn (): Collection => $this->purchaseRows($user, $products),
                collect(),
            ),
            'registeredEvents' => $registeredEvents,
            'user' => $user,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->forceFill([
            'name' => $validated['name'],
        ])->save();

        PublicCmsContentService::forgetCachedUserPayloads($request->user());

        return back()->with('account_profile_status', 'Profile updated.');
    }

    public function updateAvatar(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $path = $validated['avatar']->store('avatars', 'public');
        $avatarPath = 'storage/'.$path;
        $previousPath = $user->avatar_path;

        $user->forceFill([
            'avatar_path' => $avatarPath,
        ])->save();

        if ($previousPath && Str::startsWith($previousPath, 'storage/')) {
            Storage::disk('public')->delete(Str::after($previousPath, 'storage/'));
        }

        PublicCmsContentService::forgetCachedUserPayloads($user);

        if ($request->expectsJson()) {
            return response()->json([
                'avatar_path' => $avatarPath,
                'avatar_url' => asset($avatarPath),
                'message' => 'Avatar updated.',
            ]);
        }

        return back()->with('account_profile_status', 'Avatar updated.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('user_hub.languages', [])))],
            'preferred_currency' => ['required', 'string', Rule::in(array_keys(config('user_hub.currencies', [])))],
        ]);

        $request->user()->forceFill([
            'locale' => $validated['locale'],
            'preferred_currency' => strtoupper($validated['preferred_currency']),
        ])->save();

        PublicCmsContentService::forgetCachedUserPayloads($request->user());

        return back()->with('account_preferences_status', 'Preferences updated.');
    }

    public function pauseSubscription(Request $request, PayPalService $payPal): RedirectResponse
    {
        $user = $request->user()->load('billingProfile');
        $profile = $user->billingProfile;

        if (! $profile || ! in_array($profile->status, ['active', 'past_due'], true)) {
            return back()->with('account_billing_status', 'No active subscription to pause.');
        }

        if (filled($profile->provider_subscription_id)) {
            $payPal->suspendSubscription($profile->provider_subscription_id, 'Paused from Reny Renteria account settings.');
        }

        $profile->forceFill([
            'status' => 'paused',
            'last_synced_at' => now(),
            'metadata' => [
                ...($profile->metadata ?? []),
                'paused_at' => now()->toIso8601String(),
                'pause_source' => 'account_hub',
            ],
        ])->save();

        PublicCmsContentService::forgetCachedUserPayloads($user);

        return back()->with('account_billing_status', 'Subscription paused.');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function registeredEventCards(User $user, array $storefront, ProductCatalog $products): Collection
    {
        $ticketCards = collect();

        if ($this->hasTable('tickets') && $this->hasTable('events')) {
            $relations = ['event'];

            if ($this->hasTable('orders') && $this->hasColumn('tickets', 'order_id')) {
                $relations[] = 'order';
            }

            $ticketCards = $user->tickets()
                ->with($relations)
                ->whereIn('status', ['reserved', 'confirmed', 'checked_in'])
                ->get()
                ->filter(fn (Ticket $ticket): bool => $ticket->event?->starts_at?->isFuture() === true)
                ->sortBy(fn (Ticket $ticket): int => $ticket->event?->starts_at?->getTimestamp() ?? 0)
                ->map(fn (Ticket $ticket): array => $this->ticketEventCard($ticket, $user, $storefront, $products));
        }

        $ticketFingerprints = $ticketCards
            ->flatMap(fn (array $card): array => [$card['event_key'] ?? null, $card['fingerprint'] ?? null])
            ->filter()
            ->values();

        $rsvpCards = $this->hasTable('rsvps')
            ? Rsvp::query()
                ->where('email', Str::lower($user->email))
                ->latest()
                ->get()
                ->map(fn (Rsvp $rsvp): ?array => $this->rsvpEventCard($rsvp, $storefront))
                ->filter()
                ->reject(fn (array $card): bool => $ticketFingerprints->contains($card['event_key']) || $ticketFingerprints->contains($card['fingerprint']))
                ->values()
            : collect();

        return $ticketCards
            ->merge($rsvpCards)
            ->unique(fn (array $card): string => (string) ($card['event_key'] ?: $card['fingerprint']))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $registeredEvents
     * @return Collection<int, array<string, mixed>>
     */
    private function availableEventCards(User $user, array $storefront, ProductCatalog $products, Collection $registeredEvents): Collection
    {
        $registeredKeys = $registeredEvents
            ->flatMap(fn (array $card): array => [$card['event_key'] ?? null, $card['fingerprint'] ?? null])
            ->filter()
            ->values();

        return $this->storefrontEventSlots($storefront)
            ->map(function (array $slot) use ($products, $user): array {
                $startsAt = $this->slotStartsAt($slot);
                $eventKey = $this->slotEventKey($slot);
                $isAvailable = $this->slotTicketsAvailable($slot, $products, $user);

                return [
                    'badge' => $isAvailable ? 'Available' : 'Coming Soon',
                    'badge_class' => $isAvailable ? 'account-event-badge-available' : 'account-event-badge-muted',
                    'cta_disabled' => ! $isAvailable,
                    'cta_label' => 'Buy Now',
                    'cta_url' => $isAvailable ? $this->slotActionUrl($slot, $products, $user) : null,
                    'event_key' => $eventKey,
                    'fingerprint' => $this->eventFingerprint((string) ($slot['title'] ?? $eventKey), $startsAt),
                    'image_alt' => (string) ($slot['image_alt'] ?? $slot['title'] ?? 'Event poster'),
                    'image_url' => $this->slotImage($slot),
                    'meta' => $this->slotMeta($slot, $startsAt),
                    'starts_at' => $startsAt,
                    'title' => (string) ($slot['title'] ?? str($eventKey)->headline()),
                ];
            })
            ->filter(fn (array $card): bool => $card['starts_at'] instanceof Carbon && $card['starts_at']->isFuture())
            ->reject(fn (array $card): bool => $registeredKeys->contains($card['event_key']) || $registeredKeys->contains($card['fingerprint']))
            ->sortBy(fn (array $card): int => $card['starts_at']->getTimestamp())
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function purchaseRows(User $user, ProductCatalog $products): Collection
    {
        if (! $this->hasTable('orders')) {
            return collect();
        }

        return $user->orders()
            ->whereIn('status', ['completed', 'payment_review', 'refunded'])
            ->latest()
            ->take(12)
            ->get()
            ->map(fn (Order $order): array => [
                'name' => $this->orderProductName($order, $user, $products),
                'date' => $order->created_at?->timezone($this->accountTimezone($user))->format('F d, Y'),
                'status' => str($order->status)->replace('_', ' ')->headline()->toString(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function billingSummary(User $user, ProductCatalog $products): array
    {
        $profile = $this->hasTable('billing_profiles') ? $user->billingProfile : null;
        $royalPass = $products->find('royal', $user) ?? [];
        $latestRoyalOrder = $this->hasTable('orders')
            ? $user->orders()
                ->where('status', 'completed')
                ->where('product_key', 'royal')
                ->latest()
                ->first()
            : null;

        $amountCents = (int) ($latestRoyalOrder?->amount_cents ?? $royalPass['amount_cents'] ?? 499);
        $currency = strtoupper((string) ($latestRoyalOrder?->currency ?? $royalPass['currency'] ?? 'USD'));
        $active = $profile && in_array($profile->status, ['active', 'past_due'], true);

        return [
            'active' => $active,
            'action' => $active ? 'pause' : 'reactivate',
            'amount' => $this->moneyLabel($amountCents, $currency),
            'method' => $profile?->payment_method_summary ?: 'PayPal',
            'next_payment_date' => $active
                ? $profile->current_period_ends_at?->timezone($this->accountTimezone($user))->format('F d, Y')
                : null,
            'paypal_manage_url' => config('user_hub.paypal_manage_url'),
            'reactivate_url' => route('store.checkout', ['product' => 'royal']),
            'status' => $profile?->status ? str($profile->status)->replace('_', ' ')->headline()->toString() : 'Inactive',
            'subscription_id' => $profile?->provider_subscription_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackBillingSummary(User $user, bool $billingProfileAvailable): array
    {
        $profile = $billingProfileAvailable ? $user->billingProfile : null;
        $amountCents = (int) config('reny_catalog.products.royal.amount_cents', 499);
        $currency = strtoupper((string) config('reny_catalog.products.royal.currency', 'USD'));
        $active = $profile && in_array($profile->status, ['active', 'past_due'], true);

        return [
            'active' => $active,
            'action' => ! $billingProfileAvailable ? null : ($active ? 'pause' : 'reactivate'),
            'amount' => $this->moneyLabel($amountCents, $currency),
            'method' => $profile?->payment_method_summary ?: 'PayPal',
            'next_payment_date' => $active
                ? $profile->current_period_ends_at?->timezone($this->accountTimezone($user))->format('F d, Y')
                : null,
            'paypal_manage_url' => config('user_hub.paypal_manage_url'),
            'reactivate_url' => route('store.checkout', ['product' => 'royal']),
            'status' => $profile?->status ? str($profile->status)->replace('_', ' ')->headline()->toString() : 'Unavailable',
            'subscription_id' => $profile?->provider_subscription_id,
        ];
    }

    private function ticketEventCard(Ticket $ticket, User $user, array $storefront, ProductCatalog $products): array
    {
        $event = $ticket->event;
        $eventKey = (string) (data_get($event?->metadata, 'store_event_key') ?: $ticket->order?->product_key ?: '');
        $slot = $eventKey ? $this->storefrontEventSlot($storefront, $eventKey) : null;
        $startsAt = $event?->starts_at;

        return [
            'badge' => $ticket->order_id ? 'Purchased' : 'Registered',
            'badge_class' => $ticket->order_id ? 'account-event-badge-purchased' : 'account-event-badge-registered',
            'cta_disabled' => false,
            'cta_label' => 'View Details',
            'cta_url' => $this->registeredEventUrl($eventKey, $products, $user, $slot),
            'event_key' => $eventKey,
            'fingerprint' => $this->eventFingerprint((string) ($event?->title ?? $eventKey), $startsAt),
            'image_alt' => (string) ($slot['image_alt'] ?? $event?->title ?? 'Event poster'),
            'image_url' => $slot ? $this->slotImage($slot) : asset('images/store/rosa-dorada.png'),
            'meta' => $this->eventMeta(
                $startsAt,
                $this->accountTimezone($user, $event?->timezone),
                (string) ($event?->venue ?: 'Venue pending'),
            ),
            'starts_at' => $startsAt,
            'title' => (string) ($event?->title ?? str($eventKey)->headline()),
        ];
    }

    private function rsvpEventCard(Rsvp $rsvp, array $storefront): ?array
    {
        $slot = $this->storefrontEventSlot($storefront, (string) $rsvp->event_key);

        if (! $slot) {
            return null;
        }

        $startsAt = $this->slotStartsAt($slot);

        if (! $startsAt?->isFuture()) {
            return null;
        }

        $eventKey = $this->slotEventKey($slot);

        return [
            'badge' => 'Registered',
            'badge_class' => 'account-event-badge-registered',
            'cta_disabled' => false,
            'cta_label' => 'View Details',
            'cta_url' => route('store'),
            'event_key' => $eventKey,
            'fingerprint' => $this->eventFingerprint((string) ($slot['title'] ?? $rsvp->event_name), $startsAt),
            'image_alt' => (string) ($slot['image_alt'] ?? $rsvp->event_name ?? 'Event poster'),
            'image_url' => $this->slotImage($slot),
            'meta' => $this->slotMeta($slot, $startsAt),
            'starts_at' => $startsAt,
            'title' => (string) ($slot['title'] ?? $rsvp->event_name),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function storefrontEventSlots(array $storefront): Collection
    {
        return collect(data_get($storefront, 'slots', []))
            ->filter(fn (mixed $slot): bool => is_array($slot) && ($slot['kind'] ?? null) === 'event')
            ->values();
    }

    private function storefrontEventSlot(array $storefront, string $eventKey): ?array
    {
        return $this->storefrontEventSlots($storefront)
            ->first(fn (array $slot): bool => $this->slotEventKey($slot) === $eventKey);
    }

    private function slotEventKey(array $slot): string
    {
        return (string) ($slot['product_key'] ?? $slot['key'] ?? Str::slug((string) ($slot['title'] ?? 'event')));
    }

    private function slotStartsAt(array $slot): ?Carbon
    {
        $value = $slot['countdown_at'] ?? data_get($slot, 'event.starts_at');

        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value, config('admin.publishing_timezone', config('app.timezone')));
        } catch (Throwable) {
            return null;
        }
    }

    private function slotMeta(array $slot, ?Carbon $startsAt): string
    {
        $description = collect(preg_split('/\r\n|\r|\n/', (string) ($slot['description'] ?? '')))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->implode(' - ');

        if ($description !== '') {
            return $description;
        }

        return $this->eventMeta($startsAt, config('admin.publishing_timezone', config('app.timezone')), 'Venue pending');
    }

    private function slotImage(array $slot): string
    {
        if (filled($slot['image_url'] ?? null)) {
            return (string) $slot['image_url'];
        }

        return asset((string) ($slot['image'] ?? 'images/store/work-in-progress.png'));
    }

    private function slotTicketsAvailable(array $slot, ProductCatalog $products, User $user): bool
    {
        $actionType = (string) ($slot['action_type'] ?? 'buy');

        if ($this->isFreeEventPrice((string) ($slot['price_label'] ?? ''))) {
            return true;
        }

        if ($actionType === 'rsvp') {
            return true;
        }

        if ($actionType === 'link') {
            return filled($slot['url'] ?? null);
        }

        return $products->find($this->slotEventKey($slot), $user) !== null;
    }

    private function slotActionUrl(array $slot, ProductCatalog $products, User $user): string
    {
        $actionType = (string) ($slot['action_type'] ?? 'buy');
        $eventKey = $this->slotEventKey($slot);

        if ($actionType === 'link' && filled($slot['url'] ?? null)) {
            return (string) $slot['url'];
        }

        if ($actionType === 'buy' && $products->find($eventKey, $user) !== null) {
            return route('store.checkout', ['product' => $eventKey]);
        }

        return route('store');
    }

    private function registeredEventUrl(string $eventKey, ProductCatalog $products, User $user, ?array $slot = null): string
    {
        if ($slot) {
            $actionType = (string) ($slot['action_type'] ?? 'buy');

            if (
                $actionType === 'buy'
                && ! $this->isFreeEventPrice((string) ($slot['price_label'] ?? ''))
                && $products->find($eventKey, $user) !== null
            ) {
                return route('store.checkout', ['product' => $eventKey]);
            }

            return route('store');
        }

        if ($eventKey !== '' && $products->find($eventKey, $user) !== null) {
            return route('store.checkout', ['product' => $eventKey]);
        }

        return route('store');
    }

    private function eventMeta(?Carbon $startsAt, string $timezone, string $venue): string
    {
        $date = $startsAt?->timezone($timezone)->format('M j, Y g:i A') ?? 'Date pending';

        return "{$date} - {$venue}";
    }

    private function eventFingerprint(string $title, mixed $startsAt): string
    {
        $date = $startsAt instanceof Carbon ? $startsAt->toDateTimeString() : '';

        return Str::lower(trim($title)).'|'.$date;
    }

    private function orderProductName(Order $order, User $user, ProductCatalog $products): string
    {
        $snapshotTitle = data_get($order->metadata, 'product.title');

        if (filled($snapshotTitle)) {
            return (string) $snapshotTitle;
        }

        $product = $products->find($order->product_key, $user);

        return (string) ($product['title'] ?? str($order->product_key)->headline());
    }

    private function moneyLabel(int $amountCents, string $currency): string
    {
        $currency = strtoupper($currency);
        $symbol = config("user_hub.currencies.{$currency}.symbol", "{$currency} ");
        $amount = $amountCents / 100;

        return $symbol.number_format($amount, fmod($amount, 1.0) === 0.0 ? 0 : 2);
    }

    private function isFreeEventPrice(string $price): bool
    {
        if (preg_match('/(^|[^a-z])free([^a-z]|$)/i', $price) === 1) {
            return true;
        }

        $numeric = preg_replace('/[^0-9.]/', '', $price);

        return in_array($numeric, ['0', '0.0', '0.00'], true);
    }

    private function initials(string $name): string
    {
        return Str::of($name)
            ->explode(' ')
            ->filter()
            ->map(fn (string $part) => Str::of($part)->substr(0, 1)->upper())
            ->take(2)
            ->implode('');
    }

    private function loadBillingProfile(User $user): void
    {
        if ($this->hasTable('billing_profiles')) {
            $user->load('billingProfile');

            return;
        }

        $user->setRelation('billingProfile', null);
    }

    private function pointBalance(User $user, PointLedgerService $points): int
    {
        return $this->hasTable('point_ledger_entries') ? $points->balance($user) : 0;
    }

    private function accountTimezone(User $user, ?string $timezone = null): string
    {
        $fallback = config('admin.publishing_timezone', config('app.timezone', 'UTC'));
        $candidate = $timezone ?: $user->timezone ?: $fallback;

        try {
            new \DateTimeZone($candidate);

            return $candidate;
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function hasTable(string $table): bool
    {
        return $this->tableCache[$table] ??= Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";

        return $this->columnCache[$key] ??= ($this->hasTable($table) && Schema::hasColumn($table, $column));
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @param  TValue  $fallback
     * @return TValue
     */
    private function accountData(string $section, Closure $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            report(new RuntimeException("Account data section [{$section}] failed.", 0, $exception));

            return $fallback;
        }
    }
}
