<?php

namespace App\Models;

use App\Enums\TaxonomyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'type',
    'name',
    'slug',
    'country_code',
    'description',
    'metadata',
])]
class Taxonomy extends Model
{
    protected function casts(): array
    {
        return [
            'type' => TaxonomyType::class,
            'metadata' => 'array',
        ];
    }

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(EditorialContent::class, 'editorial_content_taxonomy')->withTimestamps();
    }
}
