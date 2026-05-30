<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteHero extends Model
{
    protected $fillable = [
        'eyebrow',
        'title',
        'subtitle',
        'body',
        'link_text',
        'badge_text',
        'image_path',
    ];

    public static function fallback(): self
    {
        return new self([
            'eyebrow' => "First\nAlbum",
            'title' => "Biggest\nLaunch",
            'subtitle' => 'Comeback Album!',
            'body' => 'A cinematic release package for Reny Renteria, built around a lead album, featured tracks, fan updates, and premium music drops.',
            'link_text' => "Visit us today at\nrenyrenteria.com",
            'badge_text' => 'THE FIRST ALBUM BANO #1',
        ]);
    }
}
