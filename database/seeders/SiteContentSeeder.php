<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\Single;
use App\Models\SiteHero;
use Illuminate\Database\Seeder;

class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        SiteHero::query()->firstOrCreate([], SiteHero::fallback()->getAttributes());

        if (Album::query()->doesntExist()) {
            Album::query()->insert(
                Album::fallbackCollection()
                    ->values()
                    ->map(fn (Album $album, int $index): array => [
                        ...$album->getAttributes(),
                        'sort_order' => $index,
                        'is_published' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->all()
            );
        }

        if (Single::query()->doesntExist()) {
            Single::query()->insert(
                Single::fallbackCollection()
                    ->values()
                    ->map(fn (Single $single, int $index): array => [
                        ...$single->getAttributes(),
                        'sort_order' => $index,
                        'is_published' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->all()
            );
        }
    }
}
