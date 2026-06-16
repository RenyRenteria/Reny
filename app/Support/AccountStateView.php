<?php

namespace App\Support;

use App\Enums\AccessState;
use App\Models\User;

class AccountStateView
{
    /**
     * @return array{
     *     state: string,
     *     badge: string,
     *     badge_class: string,
     *     member_label: string,
     *     description: string,
     *     status_label: string,
     *     action_label: string|null,
     *     action_url: string|null,
     *     analytics_id: string|null
     * }
     */
    public static function for(?User $user): array
    {
        if (! $user) {
            return [
                'state' => 'guest',
                'badge' => 'Guest',
                'badge_class' => 'account-badge-open',
                'member_label' => 'GUEST',
                'description' => 'Sign in or create an account to save purchases, tickets, points and Royal Pass access.',
                'status_label' => 'Guest',
                'action_label' => 'Sign in',
                'action_url' => route('login'),
                'analytics_id' => 'guest_sign_in',
            ];
        }

        $state = $user->accessState();
        $endsAt = self::formattedRoyalDate($user);

        return match ($state) {
            AccessState::RoyalActive => self::state(
                $state,
                'Active Royal Member',
                'ROYAL MEMBER',
                'Access active until '.($endsAt ?? 'the current period ends').'.',
                'Active',
                null,
                null,
                null,
            ),
            AccessState::RoyalGrace => self::state(
                $state,
                'Royal Grace',
                'ROYAL GRACE',
                'Royal access is in grace until '.($endsAt ?? 'the grace period ends').'.',
                'Grace period',
                'Review billing',
                route('store'),
                'royal_grace_billing',
            ),
            AccessState::RoyalExpired => self::state(
                $state,
                'Royal Expired',
                'ROYAL EXPIRED',
                'Royal Pass expired'.($endsAt ? ' on '.$endsAt : '').'. Reactivate to restore premium music, community actions and member drops.',
                'Expired',
                'Reactivate Royal Pass',
                route('store'),
                'royal_expired_reactivate',
            ),
            AccessState::PaymentFailed => self::state(
                $state,
                'Payment Failed',
                'PAYMENT FAILED',
                'Payment needs attention. Reactivate Royal Pass to restore premium access.',
                'Payment failed',
                'Reactivate Royal Pass',
                route('store'),
                'payment_failed_reactivate',
            ),
            AccessState::Refunded => self::state(
                $state,
                'Refunded',
                'REFUNDED',
                'Royal Pass access is paused after a refunded payment. Available purchases remain in your library.',
                'Refunded',
                'Reactivate Royal Pass',
                route('store'),
                'refunded_reactivate',
            ),
            AccessState::Cancelled => self::state(
                $state,
                'Royal Cancelled',
                'CANCELLED',
                'Royal Pass is cancelled. Reactivate when you are ready to restore premium access.',
                'Cancelled',
                'Reactivate Royal Pass',
                route('store'),
                'cancelled_reactivate',
            ),
            AccessState::Open => self::state(
                $state,
                'Registered Account',
                'REGISTERED',
                'Open mode: activate Royal Pass to unlock premium music, community actions and member drops.',
                'Registered',
                'Get your Royal Pass',
                route('store'),
                'registered_get_royal',
            ),
        };
    }

    /**
     * @return array{
     *     state: string,
     *     badge: string,
     *     badge_class: string,
     *     member_label: string,
     *     description: string,
     *     status_label: string,
     *     action_label: string|null,
     *     action_url: string|null,
     *     analytics_id: string|null
     * }
     */
    private static function state(
        AccessState $state,
        string $badge,
        string $memberLabel,
        string $description,
        string $statusLabel,
        ?string $actionLabel,
        ?string $actionUrl,
        ?string $analyticsId,
    ): array {
        return [
            'state' => $state->value,
            'badge' => $badge,
            'badge_class' => in_array($state, [AccessState::RoyalActive, AccessState::RoyalGrace], true)
                ? ''
                : 'account-badge-open',
            'member_label' => $memberLabel,
            'description' => $description,
            'status_label' => $statusLabel,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'analytics_id' => $analyticsId,
        ];
    }

    private static function formattedRoyalDate(User $user): ?string
    {
        return $user->royal_ends_at?->timezone($user->timezone ?: config('app.timezone'))->format('M j, Y');
    }
}
