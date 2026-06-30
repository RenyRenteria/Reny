<?php

use App\Enums\PhotoStatus;
use App\Enums\PhotoVisibility;
use App\Models\Photo;
use App\Services\Photos\PhotoLibraryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('photos') || ! extension_loaded('gd')) {
            return;
        }

        $photos = app(PhotoLibraryService::class);

        Photo::query()
            ->where('visibility', PhotoVisibility::MemberOnly->value)
            ->where('status', PhotoStatus::Active->value)
            ->whereNotNull('original_path')
            ->chunkById(25, function ($memberPhotos) use ($photos): void {
                $memberPhotos->each(fn (Photo $photo) => $photos->process($photo));
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
