<?php

namespace App\Services\PublicCms;

use App\Models\EditorialContent;
use App\Models\User;

class AccessPayloadBuilder
{
    /**
     * @return array<string, string|null>
     */
    public function music(EditorialContent $content, ?User $user): array
    {
        if ($user === null) {
            return [
                'state' => 'login_required',
                'access_state' => 'login_required',
                'access_label' => 'Free account required',
                'access_message' => 'Create a free account to listen to this music.',
                'cta_label' => 'Create free account',
                'cta_url' => route('register'),
            ];
        }

        return [
            'state' => 'ready',
            'access_state' => 'ready',
            'access_label' => 'Free account',
            'access_message' => 'Ready for this account.',
            'cta_label' => null,
            'cta_url' => null,
        ];
    }

    public function fingerprint(User $user): string
    {
        $availableUnlocks = $user->unlocks()
            ->available()
            ->orderBy('id')
            ->get(['id', 'product_key', 'source_type', 'source_id', 'updated_at'])
            ->map(fn ($unlock): string => implode(':', [
                $unlock->id,
                $unlock->product_key ?? '',
                $unlock->source_type ?? '',
                $unlock->source_id ?? '',
                $unlock->updated_at?->getTimestamp() ?? '',
            ]))
            ->implode('|');

        return sha1(implode('|', [
            'royal:'.($user->hasRoyalAccess() || $user->isStaff() ? '1' : '0'),
            'unlocks:'.$availableUnlocks,
        ]));
    }
}
