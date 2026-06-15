<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'event_id',
    'ticket_code_hash',
    'ticket_code_preview',
    'holder_name',
    'status',
    'rsvp_status',
    'purchased_at',
    'checked_in_at',
    'metadata',
])]
class Ticket extends Model
{
    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FanEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
