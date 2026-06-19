<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'poll_key',
    'option_key',
    'option_label',
])]
class CommunityPollVote extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
