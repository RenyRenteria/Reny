<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yoycol_products', function (Blueprint $table) {
            $table->id();
            $table->string('template_id')->unique();
            $table->string('design_code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->boolean('published')->default(false);
            $table->json('variants');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
        Schema::create('yoycol_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('yoycol_product_id')->constrained()->restrictOnDelete();
            $table->string('session_hash', 64);
            $table->longText('snapshot');
            $table->unsignedInteger('amount_cents');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('yoycol_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('store_order_sn')->unique();
            $table->string('provider_order_id')->nullable()->unique();
            $table->string('state')->default('awaiting_capture')->index();
            $table->longText('request_payload');
            $table->json('provider_summary')->nullable();
            $table->json('tracking')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yoycol_fulfillments');
        Schema::dropIfExists('yoycol_quotes');
        Schema::dropIfExists('yoycol_products');
    }
};
