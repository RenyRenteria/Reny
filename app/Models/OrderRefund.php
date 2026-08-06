<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'provider_refund_id',
    'amount_cents',
    'currency',
    'refunded_at',
])]
class OrderRefund extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
