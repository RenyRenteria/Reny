<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'order_id',
    'unlock_type',
    'product_key',
    'title',
    'source_type',
    'source_id',
    'status',
    'unlocked_at',
    'revoked_at',
    'metadata',
])]
class UserUnlock extends Model
{
    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
