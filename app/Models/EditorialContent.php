<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Services\PublicCmsContentService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\EditorialContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type',
    'title',
    'slug',
    'summary',
    'body',
    'status',
    'visibility',
    'needs_approval',
    'purchase_key',
    'scheduled_at',
    'approved_at',
    'published_at',
    'archived_at',
    'created_by_id',
    'updated_by_id',
    'approved_by_id',
    'published_by_id',
    'scheduled_by_id',
    'archived_by_id',
    'metadata',
])]
class EditorialContent extends Model
{
    /** @use HasFactory<EditorialContentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(fn (): bool => PublicCmsContentService::bumpCacheVersion());
        static::deleted(fn (): bool => PublicCmsContentService::bumpCacheVersion());
    }

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'status' => EditorialStatus::class,
            'visibility' => VisibilityAudience::class,
            'needs_approval' => 'boolean',
            'scheduled_at' => UtcDateTime::class,
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(EditorialAuditLog::class);
    }

    public function releaseWindows(): HasMany
    {
        return $this->hasMany(ContentReleaseWindow::class);
    }

    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(Taxonomy::class, 'editorial_content_taxonomy')->withTimestamps();
    }

    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'content_media_assets')
            ->withPivot(['role', 'sort_order', 'metadata'])
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_id');
    }

    public function scopeVisibleFor(Builder $query, ?User $user = null, ?CarbonInterface $at = null): Builder
    {
        $at = self::utcDateTime($at ?? now());
        $purchaseAccess = self::purchaseAccessFor($user);

        return $query
            ->whereIn('status', [EditorialStatus::Published->value, EditorialStatus::Scheduled->value])
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->where(function (Builder $query) use ($at): void {
                        $query
                            ->where('status', EditorialStatus::Published->value)
                            ->where(function (Builder $query) use ($at): void {
                                $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $at);
                            });
                    })
                    ->orWhere(function (Builder $query) use ($at): void {
                        $query
                            ->where('status', EditorialStatus::Scheduled->value)
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', $at);
                    });
            })
            ->where(function (Builder $query) use ($purchaseAccess, $user, $at): void {
                $query
                    ->where(function (Builder $query) use ($purchaseAccess, $user): void {
                        $query
                            ->whereDoesntHave('releaseWindows')
                            ->where(function (Builder $query) use ($purchaseAccess, $user): void {
                                self::applyAudienceConstraint(
                                    $query,
                                    'visibility',
                                    $user,
                                    $purchaseAccess,
                                    'editorial_contents.id',
                                    'editorial_contents.purchase_key',
                                );
                            });
                    })
                    ->orWhereHas('releaseWindows', function (Builder $query) use ($purchaseAccess, $user, $at): void {
                        $query
                            ->activeAt($at)
                            ->where(function (Builder $query) use ($purchaseAccess, $user): void {
                                self::applyAudienceConstraint(
                                    $query,
                                    'audience',
                                    $user,
                                    $purchaseAccess,
                                    'content_release_windows.editorial_content_id',
                                    'editorial_contents.purchase_key',
                                );
                            });
                    });
            });
    }

    public function isVisibleTo(?User $user = null, ?CarbonInterface $at = null): bool
    {
        $at = self::utcDateTime($at ?? now());

        if (in_array($this->status, [EditorialStatus::Draft, EditorialStatus::Archived], true)) {
            return false;
        }

        if ($this->scheduled_at !== null && $this->scheduled_at->gt($at)) {
            return false;
        }

        $releaseWindows = $this->relationLoaded('releaseWindows')
            ? $this->releaseWindows
            : $this->releaseWindows()->get();

        if ($releaseWindows->isNotEmpty()) {
            return $releaseWindows->contains(
                fn (ContentReleaseWindow $window): bool => $window->isActiveFor($user, $at)
            );
        }

        if ($this->status === EditorialStatus::Scheduled && $this->scheduled_at === null) {
            return false;
        }

        return $this->audienceAllows($this->visibility, $user);
    }

    public function audienceAllows(VisibilityAudience|string $audience, ?User $user = null): bool
    {
        $audience = $audience instanceof VisibilityAudience ? $audience : VisibilityAudience::from($audience);

        return match ($audience) {
            VisibilityAudience::Open => true,
            VisibilityAudience::Member => $user !== null,
            VisibilityAudience::Royal => (bool) ($user?->hasRoyalAccess() || $user?->isStaff()),
            VisibilityAudience::Purchased => $this->hasPurchasedAccess($user),
        };
    }

    public function hasPurchasedAccess(?User $user = null): bool
    {
        if ($user === null || $this->getKey() === null) {
            return false;
        }

        return $user->unlocks()
            ->available()
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query
                        ->where('source_type', 'editorial_content')
                        ->where('source_id', (string) $this->getKey());
                });

                if ($this->purchase_key !== null) {
                    $query->orWhere('product_key', $this->purchase_key);
                }
            })
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private static function audiencesFor(?User $user): array
    {
        $audiences = [VisibilityAudience::Open->value];

        if ($user !== null) {
            $audiences[] = VisibilityAudience::Member->value;
        }

        if ($user?->hasRoyalAccess()) {
            $audiences[] = VisibilityAudience::Royal->value;
        }

        if ($user?->isStaff()) {
            $audiences[] = VisibilityAudience::Royal->value;
        }

        return array_values(array_unique($audiences));
    }

    /**
     * @param  array{content_ids: array<int, int>, product_keys: array<int, string>}  $purchaseAccess
     */
    private static function applyAudienceConstraint(
        Builder $query,
        string $column,
        ?User $user,
        array $purchaseAccess,
        string $contentIdColumn,
        string $purchaseKeyColumn,
    ): void {
        $query->whereIn($column, self::audiencesFor($user));

        if ($user === null) {
            return;
        }

        $query->orWhere(function (Builder $query) use (
            $column,
            $contentIdColumn,
            $purchaseAccess,
            $purchaseKeyColumn,
        ): void {
            $query
                ->where($column, VisibilityAudience::Purchased->value)
                ->where(function (Builder $query) use (
                    $contentIdColumn,
                    $purchaseAccess,
                    $purchaseKeyColumn,
                ): void {
                    $hasPurchaseAccess = false;

                    if ($purchaseAccess['content_ids'] !== []) {
                        $query->whereIn($contentIdColumn, $purchaseAccess['content_ids']);
                        $hasPurchaseAccess = true;
                    }

                    if ($purchaseAccess['product_keys'] !== []) {
                        $method = $hasPurchaseAccess ? 'orWhereIn' : 'whereIn';
                        $query->{$method}($purchaseKeyColumn, $purchaseAccess['product_keys']);
                        $hasPurchaseAccess = true;
                    }

                    if (! $hasPurchaseAccess) {
                        $query->whereRaw('1 = 0');
                    }
                });
        });
    }

    /**
     * @return array{content_ids: array<int, int>, product_keys: array<int, string>}
     */
    private static function purchaseAccessFor(?User $user): array
    {
        if ($user === null) {
            return ['content_ids' => [], 'product_keys' => []];
        }

        $unlocks = $user->unlocks()
            ->available()
            ->get(['source_type', 'source_id', 'product_key']);

        return [
            'content_ids' => $unlocks
                ->where('source_type', 'editorial_content')
                ->pluck('source_id')
                ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all(),
            'product_keys' => $unlocks
                ->pluck('product_key')
                ->filter(fn (mixed $key): bool => is_string($key) && trim($key) !== '')
                ->map(fn (string $key): string => trim($key))
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private static function utcDateTime(CarbonInterface $value): CarbonInterface
    {
        return CarbonImmutable::instance($value)->setTimezone('UTC');
    }
}
