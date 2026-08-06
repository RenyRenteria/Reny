<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->bigInteger('refund_amount_cents')->nullable()->after('refunded_at');

            $table->index(['status', 'completed_at'], 'orders_status_completed_at_index');
            $table->index(['currency', 'completed_at'], 'orders_currency_completed_at_index');
            $table->index(['currency', 'refunded_at'], 'orders_currency_refunded_at_index');
            $table->index(['product_key', 'completed_at'], 'orders_product_completed_at_index');
        });

        DB::table('orders')
            ->whereNotNull('refunded_at')
            ->whereNull('refund_amount_cents')
            ->update(['refund_amount_cents' => DB::raw('amount_cents')]);

        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider_refund_id');
            $table->bigInteger('amount_cents');
            $table->string('currency', 3);
            $table->timestamp('refunded_at');
            $table->timestamps();

            $table->unique(['order_id', 'provider_refund_id']);
            $table->index('refunded_at');
            $table->index(['currency', 'refunded_at']);
            $table->index(['order_id', 'refunded_at']);
        });

        DB::table('orders')
            ->whereNotNull('refunded_at')
            ->orderBy('id')
            ->get(['id', 'amount_cents', 'refund_amount_cents', 'currency', 'refunded_at', 'created_at', 'updated_at'])
            ->each(function (object $order): void {
                DB::table('order_refunds')->insert([
                    'order_id' => $order->id,
                    'provider_refund_id' => 'legacy-order-'.$order->id,
                    'amount_cents' => $order->refund_amount_cents ?? $order->amount_cents,
                    'currency' => $order->currency,
                    'refunded_at' => $order->refunded_at,
                    'created_at' => $order->updated_at ?? $order->created_at,
                    'updated_at' => $order->updated_at ?? $order->created_at,
                ]);
            });

        Schema::table('access_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('schema_version')->default(1)->after('event_name');
            $table->timestamp('occurred_at')->nullable()->after('schema_version');
            $table->string('session_id', 64)->nullable()->after('occurred_at');
            $table->string('idempotency_key', 64)->nullable()->unique()->after('session_id');
            $table->string('result', 40)->nullable()->after('resource_key');

            $table->index(['event_name', 'occurred_at'], 'access_events_name_occurred_index');
            $table->index(['event_name', 'session_id', 'occurred_at'], 'access_events_name_session_occurred_index');
            $table->index(['resource_type', 'resource_key', 'occurred_at'], 'access_events_resource_occurred_index');
        });

        DB::table('access_events')
            ->whereNull('occurred_at')
            ->update(['occurred_at' => DB::raw('created_at')]);

        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'created_at'], 'users_reporting_created_index');
            $table->index(['royal_status', 'royal_ends_at'], 'users_reporting_royal_index');
        });

        Schema::table('rsvps', function (Blueprint $table) {
            $table->index('created_at', 'rsvps_reporting_created_index');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['created_at', 'event_id'], 'tickets_reporting_created_index');
            $table->index(['purchased_at', 'event_id'], 'tickets_reporting_purchased_index');
            $table->index(['checked_in_at', 'event_id'], 'tickets_reporting_checkin_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_reporting_created_index');
            $table->dropIndex('tickets_reporting_purchased_index');
            $table->dropIndex('tickets_reporting_checkin_index');
        });

        Schema::table('rsvps', function (Blueprint $table) {
            $table->dropIndex('rsvps_reporting_created_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_reporting_created_index');
            $table->dropIndex('users_reporting_royal_index');
        });

        Schema::dropIfExists('order_refunds');

        Schema::table('access_events', function (Blueprint $table) {
            $table->dropIndex('access_events_name_occurred_index');
            $table->dropIndex('access_events_name_session_occurred_index');
            $table->dropIndex('access_events_resource_occurred_index');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'schema_version',
                'occurred_at',
                'session_id',
                'idempotency_key',
                'result',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_completed_at_index');
            $table->dropIndex('orders_currency_completed_at_index');
            $table->dropIndex('orders_currency_refunded_at_index');
            $table->dropIndex('orders_product_completed_at_index');
            $table->dropColumn(['completed_at', 'refund_amount_cents']);
        });
    }
};
