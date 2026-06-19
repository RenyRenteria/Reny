<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'key',
    'name',
    'flag_label',
    'activity',
    'status',
    'created_by_id',
    'metadata',
])]
class CommunityCountryClub extends Model
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CommunityCountryClubMembership::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunityCountryClubMessage::class);
    }
}
