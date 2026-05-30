<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Single;
use App\Models\SiteHero;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $hero = Schema::hasTable('site_heroes')
            ? SiteHero::query()->first() ?? SiteHero::fallback()
            : SiteHero::fallback();

        $albums = Schema::hasTable('albums')
            ? Album::query()
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();

        $singles = Schema::hasTable('singles')
            ? Single::query()
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();

        return view('welcome', [
            'hero' => $hero,
            'albums' => $albums->isNotEmpty() ? $albums : Album::fallbackCollection(),
            'singles' => $singles->isNotEmpty() ? $singles : Single::fallbackCollection(),
        ]);
    }
}
