<?php

namespace App\Models;

use App\Services\PublicCmsContentService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'page',
    'section',
    'status',
    'payload',
    'media_asset_id',
    'updated_by_id',
    'published_by_id',
    'published_at',
])]
class SitePageSetting extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected static function booted(): void
    {
        static::saved(fn (): bool => PublicCmsContentService::bumpCacheVersion());
        static::deleted(fn (): bool => PublicCmsContentService::bumpCacheVersion());
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_id');
    }

    public function scopeForSection(Builder $query, string $page, string $section): Builder
    {
        return $query->where('page', $page)->where('section', $section);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }
}
