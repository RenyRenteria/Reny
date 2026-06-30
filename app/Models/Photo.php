<?php

namespace App\Models;

use App\Enums\PhotoStatus;
use App\Enums\PhotoVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'album_id',
    'original_disk',
    'original_path',
    'public_disk',
    'public_path',
    'blurred_disk',
    'blurred_path',
    'thumbnail_disk',
    'thumbnail_path',
    'width',
    'height',
    'visibility',
    'status',
    'order_index',
    'caption',
    'uploaded_by_id',
    'metadata',
])]
class Photo extends Model
{
    protected function casts(): array
    {
        return [
            'visibility' => PhotoVisibility::class,
            'status' => PhotoStatus::class,
            'order_index' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(PhotoAlbum::class, 'album_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PhotoStatus::Active->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw('album_id is null')
            ->orderBy('album_id')
            ->orderBy('order_index')
            ->orderBy('id');
    }

    public function canExposeOptimizedTo(?User $user): bool
    {
        if ($this->visibility === PhotoVisibility::Public) {
            return true;
        }

        return (bool) ($user?->hasRoyalAccess() || $user?->isStaff());
    }

    public function displayUrl(?User $user = null): ?string
    {
        if ($this->canExposeOptimizedTo($user)) {
            return $this->optimizedUrl($user);
        }

        return $this->blurredUrl();
    }

    public function optimizedUrl(?User $user = null): ?string
    {
        if (! $this->public_path || ! $this->public_disk) {
            return $this->legacyAssetUrl();
        }

        if ($this->visibility === PhotoVisibility::MemberOnly) {
            return $this->canExposeOptimizedTo($user) ? route('photos.image.show', $this) : null;
        }

        return Storage::disk($this->public_disk)->url($this->public_path);
    }

    public function blurredUrl(): ?string
    {
        if ($this->blurred_path && $this->blurred_disk) {
            return Storage::disk($this->blurred_disk)->url($this->blurred_path);
        }

        return $this->legacyAssetUrl();
    }

    public function thumbnailUrl(): ?string
    {
        if ($this->thumbnail_path && $this->thumbnail_disk) {
            return Storage::disk($this->thumbnail_disk)->url($this->thumbnail_path);
        }

        return $this->blurredUrl() ?: $this->optimizedUrl();
    }

    public function titleForDisplay(): string
    {
        return $this->album?->title
            ?: Str::limit($this->caption ?: (string) data_get($this->metadata, 'original_filename', 'Photo'), 80, '');
    }

    private function legacyAssetUrl(): ?string
    {
        if ($this->visibility !== PhotoVisibility::Public) {
            return null;
        }

        $path = $this->legacyAssetPath();

        return $path && is_file(public_path($path)) ? asset($path) : null;
    }

    private function legacyAssetPath(): ?string
    {
        $path = data_get($this->metadata ?? [], 'legacy_asset_path');

        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, 'images/photos/') && ! str_contains($path, '..') ? $path : null;
    }
}
