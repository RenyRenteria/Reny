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
                'public' => 'Albums, public singles, preview clips',
                'royal' => 'Royal-only songs, full premium mixes, downloads',
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
                'public' => 'Read-only previews of feed, polls, groups and chats',
                'royal' => 'Chat, voting, posting, group creation and reactions',
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
