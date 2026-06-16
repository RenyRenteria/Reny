<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\VisibilityAudience;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'editorial_content_id',
    'audience',
    'starts_at',
    'ends_at',
    'country_codes',
])]
class ContentReleaseWindow extends Model
{
    protected function casts(): array
    {
        return [
            'audience' => VisibilityAudience::class,
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'country_codes' => 'array',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'editorial_content_id');
    }

    public function isActiveFor(?User $user = null, ?CarbonInterface $at = null): bool
    {
        $at = self::utcDateTime($at ?? now());

        if ($this->starts_at !== null && $this->starts_at->gt($at)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->lte($at)) {
            return false;
        }

        if (! $this->allowsCountry($user)) {
            return false;
        }

        return $this->content?->audienceAllows($this->audience, $user) ?? false;
    }

    public function scopeActiveAt(Builder $query, CarbonInterface $at): Builder
    {
        $at = self::utcDateTime($at);

        return $query
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $at);
            });
    }

    private function allowsCountry(?User $user): bool
    {
        $countryCodes = $this->country_codes ?? [];

        if ($countryCodes === []) {
            return true;
        }

        if ($user?->country_code === null) {
            return false;
        }

        $allowedCountries = array_map('strtoupper', $countryCodes);

        return in_array(strtoupper($user->country_code), $allowedCountries, true);
    }

    private static function utcDateTime(CarbonInterface $value): CarbonInterface
    {
        return CarbonImmutable::instance($value)->setTimezone('UTC');
    }
}
