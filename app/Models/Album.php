<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Album extends Model
{
    protected $fillable = [
        'title',
        'track_count',
        'cover_label',
        'image_path',
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
            new self(['title' => 'Reny Sessions', 'track_count' => 12, 'cover_label' => 'Reny']),
            new self(['title' => 'Bano #1', 'track_count' => 10, 'cover_label' => 'Bano']),
            new self(['title' => 'First Album', 'track_count' => 8, 'cover_label' => 'First']),
            new self(['title' => 'Live Cuts', 'track_count' => 6, 'cover_label' => 'Live']),
        ]);
    }
}
