<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'venue',
    'address',
    'timezone',
    'starts_at',
    'ends_at',
    'status',
    'metadata',
])]
class FanEvent extends Model
{
    protected $table = 'events';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'event_id');
    }
}
