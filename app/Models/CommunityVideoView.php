<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'post_key',
    'video_key',
    'viewer_key',
    'user_id',
])]
class CommunityVideoView extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
