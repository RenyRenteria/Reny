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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('paypal');
            $table->string('provider_order_id')->unique();
            $table->string('product_key');
            $table->bigInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('completed');
            $table->boolean('grants_royal_month')->default(true);
            $table->timestamp('royal_granted_until')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name');
            $table->string('resource_type')->nullable();
            $table->string('resource_key')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_events');
        Schema::dropIfExists('orders');
    }
};
