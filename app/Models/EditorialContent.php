<?php

namespace App\Models;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use Carbon\CarbonInterface;
use Database\Factories\EditorialContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

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

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'status' => EditorialStatus::class,
            'visibility' => VisibilityAudience::class,
            'needs_approval' => 'boolean',
            'scheduled_at' => 'datetime',
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
        $at ??= now();

        return $query
            ->whereIn('status', [EditorialStatus::Published->value, EditorialStatus::Scheduled->value])
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->where(function (Builder $query) use ($at): void {
                        $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $at);
                    })
                    ->orWhereHas('releaseWindows', function (Builder $query) use ($at): void {
                        $query->activeAt($at);
                    });
            })
            ->where(function (Builder $query) use ($user, $at): void {
                $query
                    ->where(function (Builder $query) use ($user): void {
                        $query
                            ->whereDoesntHave('releaseWindows')
                            ->where(function (Builder $query) use ($user): void {
                                self::applyAudienceConstraint($query, 'visibility', $user);
                            });
                    })
                    ->orWhereHas('releaseWindows', function (Builder $query) use ($user, $at): void {
                        $query
                            ->activeAt($at)
                            ->where(function (Builder $query) use ($user): void {
                                self::applyAudienceConstraint($query, 'audience', $user);
                            });
                    });
            });
    }

    public function isVisibleTo(?User $user = null, ?CarbonInterface $at = null): bool
    {
        $at ??= now();

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
            VisibilityAudience::Member, VisibilityAudience::Royal => $user?->hasRoyalAccess() ?? false,
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

        if ($user?->hasRoyalAccess()) {
            $audiences[] = VisibilityAudience::Member->value;
            $audiences[] = VisibilityAudience::Royal->value;
        }

        return $audiences;
    }

    private static function applyAudienceConstraint(Builder $query, string $column, ?User $user): void
    {
        $query->whereIn($column, self::audiencesFor($user));

        if ($user === null) {
            return;
        }

        $query->orWhere(function (Builder $query) use ($column, $user): void {
            $query
                ->where($column, VisibilityAudience::Purchased->value)
                ->whereExists(function (QueryBuilder $query) use ($user): void {
                    $query
                        ->selectRaw('1')
                        ->from('user_unlocks')
                        ->where('user_unlocks.user_id', $user->getKey())
                        ->where('user_unlocks.status', 'available')
                        ->where(function (QueryBuilder $query): void {
                            $query
                                ->where(function (QueryBuilder $query): void {
                                    $query
                                        ->where('user_unlocks.source_type', 'editorial_content')
                                        ->whereColumn('user_unlocks.source_id', 'editorial_contents.id');
                                })
                                ->orWhereColumn('user_unlocks.product_key', 'editorial_contents.purchase_key');
                        });
                });
        });
    }
}
