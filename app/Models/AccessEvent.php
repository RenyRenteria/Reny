<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'event_name',
    'schema_version',
    'occurred_at',
    'session_id',
    'visitor_id',
    'traffic_source',
    'traffic_medium',
    'traffic_campaign',
    'device_category',
    'country_code',
    'idempotency_key',
    'resource_type',
    'resource_key',
    'result',
    'metadata',
])]
class AccessEvent extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
