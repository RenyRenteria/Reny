<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'provider',
    'provider_customer_id',
    'provider_subscription_id',
    'status',
    'payment_method_summary',
    'current_period_ends_at',
    'grace_ends_at',
    'failed_payment_at',
    'last_synced_at',
    'metadata',
])]
class BillingProfile extends Model
{
    protected function casts(): array
    {
        return [
            'current_period_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'failed_payment_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
