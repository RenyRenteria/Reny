<?php

use App\Services\PublicVideoCatalogSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Populate production with the catalog that was already public as a static fallback.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        app(PublicVideoCatalogSeeder::class)->seed();
    }

    /**
     * Imported editorial content can be edited after deploy, so rollback never deletes it.
     */
    public function down(): void {}
};
