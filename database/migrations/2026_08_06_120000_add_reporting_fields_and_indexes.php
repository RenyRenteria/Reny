<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status')->index();
            $table->index(['status', 'currency', 'created_at'], 'orders_reporting_completed_index');
            $table->index(['refunded_at', 'currency'], 'orders_reporting_refunds_index');
            $table->index(['product_key', 'status'], 'orders_reporting_products_index');
        });

        Schema::table('access_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('schema_version')->default(1)->after('event_name');
            $table->string('session_key', 64)->nullable()->after('resource_key');
            $table->string('idempotency_key', 64)->nullable()->unique()->after('session_key');
            $table->timestamp('client_occurred_at')->nullable()->after('idempotency_key');
            $table->index(['event_name', 'created_at'], 'access_events_reporting_event_index');
            $table->index(['resource_type', 'resource_key', 'created_at'], 'access_events_reporting_resource_index');
            $table->index(['session_key', 'created_at'], 'access_events_reporting_session_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'created_at'], 'users_reporting_created_index');
            $table->index(['royal_status', 'royal_ends_at'], 'users_reporting_royal_index');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['event_id', 'purchased_at'], 'tickets_reporting_purchased_index');
            $table->index(['event_id', 'checked_in_at'], 'tickets_reporting_checkin_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_reporting_purchased_index');
            $table->dropIndex('tickets_reporting_checkin_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_reporting_created_index');
            $table->dropIndex('users_reporting_royal_index');
        });

        Schema::table('access_events', function (Blueprint $table) {
            $table->dropIndex('access_events_reporting_event_index');
            $table->dropIndex('access_events_reporting_resource_index');
            $table->dropIndex('access_events_reporting_session_index');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['schema_version', 'session_key', 'idempotency_key', 'client_occurred_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_reporting_completed_index');
            $table->dropIndex('orders_reporting_refunds_index');
            $table->dropIndex('orders_reporting_products_index');
            $table->dropColumn('completed_at');
        });
    }
};
