<?php

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
        Schema::create('editorial_contents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->index();
            $table->string('title');
            $table->string('slug');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('visibility', 32)->default('open')->index();
            $table->boolean('needs_approval')->default(false)->index();
            $table->string('purchase_key')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scheduled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['type', 'slug']);
            $table->index(['type', 'status']);
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('content_release_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->string('audience', 32)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->json('country_codes')->nullable();
            $table->timestamps();

            $table->index(['editorial_content_id', 'audience']);
            $table->index(['audience', 'starts_at']);
        });

        Schema::create('taxonomies', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->index();
            $table->string('name');
            $table->string('slug');
            $table->string('country_code', 2)->nullable()->index();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['type', 'slug']);
        });

        Schema::create('editorial_content_taxonomy', function (Blueprint $table) {
            $table->foreignId('editorial_content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['editorial_content_id', 'taxonomy_id']);
        });

        Schema::create('editorial_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_content_id')->nullable()->constrained('editorial_contents')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 48)->index();
            $table->json('changes')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['editorial_content_id', 'action']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editorial_audit_logs');
        Schema::dropIfExists('editorial_content_taxonomy');
        Schema::dropIfExists('taxonomies');
        Schema::dropIfExists('content_release_windows');
        Schema::dropIfExists('editorial_contents');
    }
};
