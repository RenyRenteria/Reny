<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Single extends Model
{
    protected $fillable = [
        'title',
        'artist',
        'image_path',
        'audio_path',
        'audio_url',
        'sort_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return Collection<int, self>
     */
    public static function fallbackCollection(): Collection
    {
        return collect([
            new self(['title' => 'Biggest Launch', 'artist' => 'Reny Renteria']),
            new self(['title' => 'Comeback Album', 'artist' => 'Reny Renteria']),
            new self(['title' => 'First Drop', 'artist' => 'Reny Renteria']),
            new self(['title' => 'VIP Mix', 'artist' => 'Exclusive']),
        ]);
    }
}
