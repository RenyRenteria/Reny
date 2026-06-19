<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('post_key', 160);
            $table->string('reaction')->default('like');
            $table->timestamps();

            $table->unique(['user_id', 'post_key', 'reaction'], 'community_post_reactions_unique');
            $table->index(['post_key', 'reaction']);
        });

        Schema::create('community_post_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('post_key', 160)->index();
            $table->text('body');
            $table->string('status')->default('visible')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('community_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('poll_key', 160);
            $table->string('option_key', 160);
            $table->string('option_label')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'poll_key'], 'community_poll_votes_user_poll_unique');
            $table->index(['poll_key', 'option_key']);
        });

        Schema::create('community_country_clubs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 160)->unique();
            $table->string('name');
            $table->string('flag_label', 12)->nullable();
            $table->string('activity')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('community_country_club_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_country_club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['community_country_club_id', 'user_id'],
                'community_country_club_memberships_unique'
            );
        });

        Schema::create('community_country_club_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_country_club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('status')->default('visible')->index();
            $table->timestamps();

            $table->index(['community_country_club_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_country_club_messages');
        Schema::dropIfExists('community_country_club_memberships');
        Schema::dropIfExists('community_country_clubs');
        Schema::dropIfExists('community_poll_votes');
        Schema::dropIfExists('community_post_replies');
        Schema::dropIfExists('community_post_reactions');
    }
};
