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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('country_code', 2)->nullable()->after('avatar_path');
            $table->string('locale', 12)->default('en')->after('country_code');
            $table->string('timezone')->default('America/Panama')->after('locale');
            $table->string('preferred_currency', 3)->default('USD')->after('timezone');
            $table->string('bio', 240)->nullable()->after('preferred_currency');
        });

        Schema::create('billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('paypal');
            $table->string('provider_customer_id')->nullable()->index();
            $table->string('provider_subscription_id')->nullable()->index();
            $table->string('status')->default('inactive')->index();
            $table->string('payment_method_summary')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('failed_payment_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('user_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unlock_type');
            $table->string('product_key')->nullable()->index();
            $table->string('title');
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('status')->default('available')->index();
            $table->timestamp('unlocked_at');
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->unique(['user_id', 'source_type', 'source_id', 'product_key'], 'user_unlocks_source_unique');
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->string('timezone')->default('America/Panama');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('scheduled')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('ticket_code_hash')->unique();
            $table->string('ticket_code_preview', 12)->nullable();
            $table->string('holder_name');
            $table->string('status')->default('confirmed')->index();
            $table->string('rsvp_status')->default('confirmed')->index();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['event_id', 'status']);
        });

        Schema::create('point_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->integer('delta');
            $table->string('status')->default('posted')->index();
            $table->integer('balance_after');
            $table->string('idempotency_key')->unique();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('actor_type')->default('system');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_ledger_entries');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('events');
        Schema::dropIfExists('user_unlocks');
        Schema::dropIfExists('billing_profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username',
                'avatar_path',
                'country_code',
                'locale',
                'timezone',
                'preferred_currency',
                'bio',
            ]);
        });
    }
};
