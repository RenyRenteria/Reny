<?php

namespace Database\Seeders;

use App\Models\YoycolProduct;
use Illuminate\Database\Seeder;

class QaMerchProductSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local', 'testing') && config('database.default') === 'sqlite', 403);

        YoycolProduct::updateOrCreate(['template_id' => 'qa-browser-hoodie'], [
            'design_code' => 'QA-HOODIE', 'title' => 'QA Merch Hoodie', 'price_cents' => 4800,
            'published' => true, 'synced_at' => now(), 'image_url' => '/newlanding-assets/merch/02-hoodie-rubi-480.webp',
            'variants' => [
                ['id' => 'medium', 'sku' => 'QA-M', 'label' => 'Ruby / M', 'cost_cents' => 2000],
                ['id' => 'large', 'sku' => 'QA-L', 'label' => 'Ruby / L', 'cost_cents' => 2000],
            ],
        ]);
    }
}
