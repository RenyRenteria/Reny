<?php

namespace App\Support;

use App\Models\User;

class EntitlementMatrix
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function sections(): array
    {
        return [
            'music' => [
                'public' => 'Published catalog metadata; playback requires a free account',
                'royal' => 'No additional music gate beyond the authenticated account',
            ],
            'videos' => [
                'public' => 'Trailers, public videos, metadata',
                'royal' => 'Members-only video drops and full premium playback',
            ],
            'photos' => [
                'public' => 'Public gallery and preview images',
                'royal' => 'Full premium sets and protected downloads',
            ],
            'community' => [
                'public' => 'Full read-only feed, comments, groups and chats',
                'royal' => 'Chat messages, comments, votes, group participation and reactions',
            ],
            'store' => [
                'public' => 'Public products and guest checkout',
                'royal' => 'Royal early access and member-only products when enabled',
            ],
            'user_hub' => [
                'public' => 'Reactivation screen for expired or open accounts',
                'royal' => 'Membership, billing, history, points and premium access state',
            ],
            'admin' => [
                'public' => 'No public access',
                'royal' => 'Staff-only CMS, moderation and publishing tools',
            ],
        ];
    }

    public static function canUseRoyalFeature(?User $user): bool
    {
        return (bool) ($user?->hasRoyalAccess() || $user?->isStaff());
    }
}
