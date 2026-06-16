<?php

use App\Enums\MediaProcessingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 32)->index();
            $table->string('title')->nullable();
            $table->string('disk', 64);
            $table->string('path')->nullable();
            $table->string('original_filename');
            $table->string('mime_type', 160)->nullable();
            $table->string('extension', 24)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 96)->nullable();
            $table->boolean('is_public')->default(true)->index();
            $table->string('alt_text', 180)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('processing_status', 32)->default(MediaProcessingStatus::Ready->value)->index();
            $table->string('mux_upload_id')->nullable()->unique();
            $table->string('mux_asset_id')->nullable()->unique();
            $table->string('mux_playback_id')->nullable()->index();
            $table->string('mux_status', 64)->nullable();
            $table->text('mux_error')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'processing_status']);
            $table->index(['uploaded_by_id', 'created_at']);
        });

        Schema::create('content_media_assets', function (Blueprint $table) {
            $table->foreignId('editorial_content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('role', 48)->default('primary');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->primary(['editorial_content_id', 'media_asset_id', 'role']);
            $table->index(['media_asset_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_media_assets');
        Schema::dropIfExists('media_assets');
    }
};
