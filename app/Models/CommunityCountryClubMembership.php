<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'community_country_club_id',
    'user_id',
    'status',
    'joined_at',
])]
class CommunityCountryClubMembership extends Model
{
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(CommunityCountryClub::class, 'community_country_club_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
