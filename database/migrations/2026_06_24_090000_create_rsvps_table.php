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
        Schema::create('rsvps', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 120);
            $table->string('event_name');
            $table->string('name');
            $table->string('email');
            $table->string('country', 100);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['event_key', 'email']);
            $table->index(['event_key', 'created_at']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rsvps');
    }
};
