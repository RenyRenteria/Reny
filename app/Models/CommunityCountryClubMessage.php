<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'community_country_club_id',
    'user_id',
    'body',
    'status',
])]
class CommunityCountryClubMessage extends Model
{
    public function club(): BelongsTo
    {
        return $this->belongsTo(CommunityCountryClub::class, 'community_country_club_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
