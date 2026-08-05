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

    public const ROLE_FAN = 'fan';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_ARTIST_ADMIN = 'artist_admin';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_MODERATOR = 'moderator';

    public const ADMIN_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_ARTIST_ADMIN,
        self::ROLE_EDITOR,
    ];

    public const PUBLISHING_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_ARTIST_ADMIN,
    ];

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

    public function editorialContents(): HasMany
    {
        return $this->hasMany(EditorialContent::class, 'created_by_id');
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'uploaded_by_id');
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(UserUnlock::class);
    }

    public function accessState(): AccessState
    {
        if ($this->royal_status === AccessState::Refunded->value) {
            return AccessState::Refunded;
        }

        if (in_array($this->royal_status, [AccessState::PaymentFailed->value, 'on_hold'], true)) {
            return AccessState::PaymentFailed;
        }

        if ($this->royal_status === AccessState::Cancelled->value) {
            return AccessState::Cancelled;
        }

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
        return in_array($this->role, [
            self::ROLE_ADMIN,
            self::ROLE_ARTIST_ADMIN,
            self::ROLE_EDITOR,
            self::ROLE_MODERATOR,
        ], true);
    }

    public function canAccessAdmin(): bool
    {
        return in_array($this->role, self::ADMIN_ROLES, true);
    }

    public function canPublishContent(): bool
    {
        return in_array($this->role, self::PUBLISHING_ROLES, true);
    }

    public function canManageCommunityPosts(): bool
    {
        return $this->canAccessAdmin();
    }
}
