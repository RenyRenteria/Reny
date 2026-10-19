<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YoycolQuote extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'encrypted:array', 'expires_at' => 'datetime', 'consumed_at' => 'datetime', 'amount_cents' => 'integer'];
    }
}
