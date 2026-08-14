<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_video_views', function (Blueprint $table) {
            $table->id();
            $table->string('post_key', 160);
            $table->string('video_key', 80);
            $table->char('viewer_key', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['video_key', 'viewer_key'], 'community_video_views_viewer_index');
            $table->index(['post_key', 'video_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_video_views');
    }
};
