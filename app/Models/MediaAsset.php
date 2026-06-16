<?php

namespace App\Models;

use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'type',
    'title',
    'disk',
    'path',
    'original_filename',
    'mime_type',
    'extension',
    'size_bytes',
    'checksum',
    'is_public',
    'alt_text',
    'duration_seconds',
    'width',
    'height',
    'processing_status',
    'mux_upload_id',
    'mux_asset_id',
    'mux_playback_id',
    'mux_status',
    'mux_error',
    'uploaded_by_id',
    'metadata',
])]
class MediaAsset extends Model
{
    protected function casts(): array
    {
        return [
            'type' => MediaAssetType::class,
            'size_bytes' => 'integer',
            'is_public' => 'boolean',
            'duration_seconds' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'processing_status' => MediaProcessingStatus::class,
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MediaAsset $asset): void {
            $asset->uuid ??= (string) Str::uuid();
        });
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function editorialContents(): BelongsToMany
    {
        return $this->belongsToMany(EditorialContent::class, 'content_media_assets')
            ->withPivot(['role', 'sort_order', 'metadata'])
            ->withTimestamps();
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('processing_status', MediaProcessingStatus::Ready->value);
    }

    public function publicUrl(): ?string
    {
        if (! $this->is_public || $this->path === null || $this->disk !== config('media.public_disk', 'public')) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->path);
    }
}
