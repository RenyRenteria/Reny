<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_video_views', function (Blueprint $table) {
            $table->dropUnique('community_video_views_viewer_unique');
            $table->index(['video_key', 'viewer_key'], 'community_video_views_viewer_index');
        });
    }

    public function down(): void
    {
        Schema::table('community_video_views', function (Blueprint $table) {
            $table->dropIndex('community_video_views_viewer_index');
            $table->unique(['video_key', 'viewer_key'], 'community_video_views_viewer_unique');
        });
    }
};
