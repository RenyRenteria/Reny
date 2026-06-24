<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event_key',
    'event_name',
    'name',
    'email',
    'country',
    'metadata',
])]
class Rsvp extends Model
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
