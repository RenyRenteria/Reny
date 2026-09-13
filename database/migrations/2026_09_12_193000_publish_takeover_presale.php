<?php

use App\Services\TakeoverPresaleSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Follow the existing public-catalog import pattern; tests seed explicitly.
        if (! app()->runningUnitTests()) {
            app(TakeoverPresaleSeeder::class)->seed();
        }
    }

    // Preserve the event and its purchase history on rollback.
    public function down(): void {}
};
