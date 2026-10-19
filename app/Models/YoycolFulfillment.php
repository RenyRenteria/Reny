<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YoycolFulfillment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['request_payload'];

    protected function casts(): array
    {
        return ['request_payload' => 'encrypted:array', 'provider_summary' => 'array', 'tracking' => 'array', 'attempted_at' => 'datetime', 'synced_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customerStatus(): string
    {
        if ($this->order->status === 'refunded') {
            return 'Refunded';
        }

        return match ($this->state) {
            'shipped' => 'Shipped',
            'production' => 'In production',
            'cancelled' => 'Cancelled',
            default => 'Preparing your order',
        };
    }
}
