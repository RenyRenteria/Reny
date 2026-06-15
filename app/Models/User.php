<?php

namespace App\Models;

use App\Enums\AccessState;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'username',
    'email',
    'phone',
    'avatar_path',
    'country_code',
    'locale',
    'timezone',
    'preferred_currency',
    'bio',
    'password',
    'role',
    'royal_status',
    'royal_ends_at',
])]
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

    public function billingProfile(): HasOne
    {
        return $this->hasOne(BillingProfile::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function pointLedgerEntries(): HasMany
    {
        return $this->hasMany(PointLedgerEntry::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(UserUnlock::class);
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
