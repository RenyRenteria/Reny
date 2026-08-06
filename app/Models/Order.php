<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'provider',
    'provider_order_id',
    'provider_capture_id',
    'product_key',
    'amount_cents',
    'currency',
    'status',
    'completed_at',
    'grants_royal_month',
    'royal_granted_until',
    'refunded_at',
    'metadata',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'grants_royal_month' => 'boolean',
            'royal_granted_until' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
