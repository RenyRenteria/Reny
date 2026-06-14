<?php

namespace App\Models;

use App\Enums\AccessState;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'royal_status', 'royal_ends_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'royal_ends_at' => 'datetime',
        ];
    }

    public function accessState(): AccessState
    {
        if ($this->royal_status === AccessState::RoyalActive->value && $this->royal_ends_at?->isFuture()) {
            return AccessState::RoyalActive;
        }

        if ($this->royal_status === AccessState::RoyalGrace->value && $this->royal_ends_at?->isFuture()) {
            return AccessState::RoyalGrace;
        }

        if ($this->royal_status === AccessState::RoyalExpired->value || $this->royal_ends_at?->isPast()) {
            return AccessState::RoyalExpired;
        }

        return AccessState::Open;
    }

    public function hasRoyalAccess(): bool
    {
        return in_array($this->accessState(), [AccessState::RoyalActive, AccessState::RoyalGrace], true);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'editor', 'moderator'], true);
    }
}
