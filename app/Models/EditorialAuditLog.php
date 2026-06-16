<?php

namespace App\Models;

use App\Enums\EditorialAuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'editorial_content_id',
    'actor_id',
    'action',
    'changes',
    'snapshot',
])]
class EditorialAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => EditorialAuditAction::class,
            'changes' => 'array',
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'editorial_content_id');
    }
}
