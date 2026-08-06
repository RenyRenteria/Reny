<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'event_name',
    'schema_version',
    'resource_type',
    'resource_key',
    'session_key',
    'idempotency_key',
    'client_occurred_at',
    'metadata',
])]
class AccessEvent extends Model
{
    protected function casts(): array
    {
        return [
            'client_occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
