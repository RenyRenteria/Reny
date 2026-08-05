<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'description',
    'cover_photo_id',
    'order_index',
    'created_by_id',
    'updated_by_id',
    'metadata',
])]
class PhotoAlbum extends Model
{
    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'album_id')->orderBy('order_index')->orderBy('id');
    }

    public function coverPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'cover_photo_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
