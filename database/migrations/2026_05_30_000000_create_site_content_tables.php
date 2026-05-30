<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_heroes', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('body')->nullable();
            $table->string('link_text')->nullable();
            $table->string('badge_text')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedSmallInteger('track_count')->default(0);
            $table->string('cover_label')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('singles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('image_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('audio_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('singles');
        Schema::dropIfExists('albums');
        Schema::dropIfExists('site_heroes');
    }
};
