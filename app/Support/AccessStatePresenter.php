<?php

namespace App\Support;

use App\Enums\AccessState;
use App\Models\User;
use Illuminate\Http\Request;

class AccessStatePresenter
{
    /**
     * @return array{
     *     state: string,
     *     label: string,
     *     sidebar_label: string,
     *     badge_class: string,
     *     account_copy: string,
     *     paywall_title: string,
     *     paywall_copy: string,
     *     primary_label: string,
     *     primary_url: string,
     *     primary_action: string,
     *     secondary_label: string|null,
     *     secondary_url: string|null,
     *     source_route: string|null
     * }
     */
    public static function for(?User $user, ?string $sourceRoute = null): array
    {
        $state = $user?->accessState()->value ?? 'guest';
        $sourceRoute = self::cleanPath($sourceRoute);

        return match ($state) {
            AccessState::RoyalActive->value => [
                'state' => $state,
                'label' => 'Active Royal Member',
                'sidebar_label' => 'ROYAL MEMBER',
                'badge_class' => 'account-badge-active',
                'account_copy' => 'Royal Pass is active for premium music, community actions and member drops.',
                'paywall_title' => 'Royal access active',
                'paywall_copy' => 'Your Royal Pass is active. Continue into the protected experience.',
                'primary_label' => 'Continue',
                'primary_url' => $sourceRoute ?: route('account.show'),
                'primary_action' => 'continue',
                'secondary_label' => null,
                'secondary_url' => null,
                'source_route' => $sourceRoute,
            ],
            AccessState::RoyalGrace->value => [
                'state' => $state,
                'label' => 'Royal Grace Period',
                'sidebar_label' => 'ROYAL MEMBER',
                'badge_class' => 'account-badge-active',
                'account_copy' => 'Royal Pass is in grace and premium access remains available.',
                'paywall_title' => 'Royal access active',
                'paywall_copy' => 'Your Royal Pass is still available during grace period.',
                'primary_label' => 'Continue',
                'primary_url' => $sourceRoute ?: route('account.show'),
                'primary_action' => 'continue',
                'secondary_label' => null,
                'secondary_url' => null,
                'source_route' => $sourceRoute,
            ],
            AccessState::RoyalExpired->value => [
                'state' => $state,
                'label' => 'Royal Expired',
                'sidebar_label' => 'ROYAL EXPIRED',
                'badge_class' => 'account-badge-expired',
                'account_copy' => 'Your Royal Pass period ended. Reactivate to restore premium music, community actions and member drops.',
                'paywall_title' => 'Reactivate Royal Pass',
                'paywall_copy' => 'This area is still protected. Reactivate Royal Pass to continue without exposing premium content.',
                'primary_label' => 'Reactivate Royal Pass',
                'primary_url' => route('store'),
                'primary_action' => 'reactivate',
                'secondary_label' => 'Back to account',
                'secondary_url' => route('account.show'),
                'source_route' => $sourceRoute,
            ],
            AccessState::PaymentFailed->value => [
                'state' => $state,
                'label' => 'Payment Action Needed',
                'sidebar_label' => 'PAYMENT NEEDED',
                'badge_class' => 'account-badge-payment',
                'account_copy' => 'Royal access is paused because the latest payment needs attention.',
                'paywall_title' => 'Update payment to continue',
                'paywall_copy' => 'Premium content stays locked until the payment issue is resolved.',
                'primary_label' => 'Update payment',
                'primary_url' => route('store'),
                'primary_action' => 'update_payment',
                'secondary_label' => 'Back to account',
                'secondary_url' => route('account.show'),
                'source_route' => $sourceRoute,
            ],
            AccessState::Refunded->value => [
                'state' => $state,
                'label' => 'Royal Refunded',
                'sidebar_label' => 'ROYAL REFUNDED',
                'badge_class' => 'account-badge-refunded',
                'account_copy' => 'This Royal Pass was refunded, so premium access and related unlocks are paused.',
                'paywall_title' => 'Royal Pass was refunded',
                'paywall_copy' => 'Premium content stays locked after a refund. Buy a new pass to continue.',
                'primary_label' => 'Buy Royal Pass again',
                'primary_url' => route('store'),
                'primary_action' => 'repurchase',
                'secondary_label' => 'Back to account',
                'secondary_url' => route('account.show'),
                'source_route' => $sourceRoute,
            ],
            'guest' => [
                'state' => 'guest',
                'label' => 'Guest',
                'sidebar_label' => 'GUEST',
                'badge_class' => 'account-badge-open',
                'account_copy' => 'Sign in or create an account to manage Royal Pass.',
                'paywall_title' => 'Sign in to continue',
                'paywall_copy' => 'Royal content is protected. Sign in or create an account before continuing.',
                'primary_label' => 'Sign in',
                'primary_url' => route('login'),
                'primary_action' => 'login',
                'secondary_label' => 'Create account',
                'secondary_url' => route('register'),
                'source_route' => $sourceRoute,
            ],
            default => [
                'state' => AccessState::Open->value,
                'label' => 'Open Account',
                'sidebar_label' => 'OPEN ACCESS',
                'badge_class' => 'account-badge-open',
                'account_copy' => 'Open mode: activate Royal Pass to unlock premium music, community actions and member drops.',
                'paywall_title' => 'Royal Pass required',
                'paywall_copy' => 'This area is protected for active Royal members.',
                'primary_label' => 'Get your Royal Pass',
                'primary_url' => route('store'),
                'primary_action' => 'upgrade',
                'secondary_label' => 'Back to account',
                'secondary_url' => $user ? route('account.show') : route('login'),
                'source_route' => $sourceRoute,
            ],
        };
    }

    public static function sourceFromRequest(Request $request): string
    {
        return self::pathFromUrl($request->fullUrl()) ?? '/';
    }

    public static function pathFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return self::cleanPath($path);
    }

    private static function cleanPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = '/'.ltrim($path, '/');

        return $path === '//' ? '/' : $path;
    }
}
