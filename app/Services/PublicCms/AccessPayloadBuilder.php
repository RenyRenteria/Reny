<?php

namespace App\Services\PublicCms;

use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\User;

class AccessPayloadBuilder
{
    /**
     * @return array<string, string|null>
     */
    public function music(EditorialContent $content, ?User $user): array
    {
        if ($content->isVisibleTo($user)) {
            return [
                'state' => 'ready',
                'access_state' => 'ready',
                'access_label' => match ($content->visibility) {
                    VisibilityAudience::Open => 'Open',
                    VisibilityAudience::Member => 'Member',
                    VisibilityAudience::Royal => 'Royal',
                    VisibilityAudience::Purchased => 'Unlocked',
                },
                'access_message' => 'Ready for this account.',
                'cta_label' => null,
                'cta_url' => null,
            ];
        }

        if ($user === null) {
            return [
                'state' => 'login_required',
                'access_state' => 'login_required',
                'access_label' => 'Login required',
                'access_message' => 'Sign in to check access for this music item.',
                'cta_label' => 'Sign in',
                'cta_url' => route('login'),
            ];
        }

        if ($content->visibility === VisibilityAudience::Royal) {
            return [
                'state' => 'royal_required',
                'access_state' => 'royal_required',
                'access_label' => 'Royal required',
                'access_message' => 'This music item requires an active Royal Pass.',
                'cta_label' => 'Get Royal Pass',
                'cta_url' => route('store'),
            ];
        }

        if ($content->visibility === VisibilityAudience::Purchased) {
            return [
                'state' => 'content_locked',
                'access_state' => 'content_locked',
                'access_label' => 'Locked',
                'access_message' => 'This music item unlocks after purchase.',
                'cta_label' => 'Open store',
                'cta_url' => route('store'),
            ];
        }

        return [
            'state' => 'content_locked',
            'access_state' => 'content_locked',
            'access_label' => 'Locked',
            'access_message' => 'This release window is not open for this account.',
            'cta_label' => 'View details',
            'cta_url' => $this->musicDetailUrl($content),
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

    private function musicDetailUrl(EditorialContent $content): string
    {
        return in_array($content->type, ContentQuery::MUSIC_ALBUM_TYPES, true)
            ? route('music.albums.show', $content)
            : route('public.content.show', $content);
    }
}
