<?php

namespace App\Services;

use App\Enums\AccessState;
use App\Enums\VisibilityAudience;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CmsPreviewContext
{
    private ?VisibilityAudience $audience = null;

    private ?User $viewer = null;

    public function audience(): ?VisibilityAudience
    {
        return $this->audience;
    }

    public function active(): bool
    {
        return $this->audience !== null;
    }

    public function viewer(): ?User
    {
        return $this->viewer;
    }

    public function run(VisibilityAudience|string $audience, callable $callback): mixed
    {
        $previousAudience = $this->audience;
        $previousViewer = $this->viewer;
        $previousAuthUser = Auth::user();
        $this->audience = $audience instanceof VisibilityAudience
            ? $audience
            : VisibilityAudience::from($audience);
        $this->viewer = $this->previewViewer($this->audience);

        if ($this->viewer) {
            Auth::guard()->setUser($this->viewer);
        } else {
            Auth::guard()->forgetUser();
        }

        try {
            return $callback();
        } finally {
            $this->audience = $previousAudience;
            $this->viewer = $previousViewer;

            if ($previousAuthUser) {
                Auth::guard()->setUser($previousAuthUser);
            } else {
                Auth::guard()->forgetUser();
            }
        }
    }

    private function previewViewer(VisibilityAudience $audience): ?User
    {
        if ($audience === VisibilityAudience::Open) {
            return null;
        }

        return new User([
            'name' => match ($audience) {
                VisibilityAudience::Member => 'Member preview',
                VisibilityAudience::Royal => 'Royal preview',
                VisibilityAudience::Purchased => 'Purchased preview',
                default => 'Preview',
            },
            'email' => 'cms-preview@example.invalid',
            'role' => User::ROLE_FAN,
            'royal_status' => $audience === VisibilityAudience::Royal
                ? AccessState::RoyalActive->value
                : AccessState::Open->value,
            'royal_ends_at' => $audience === VisibilityAudience::Royal ? now()->addDay() : null,
        ]);
    }
}
