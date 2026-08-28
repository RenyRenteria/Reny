<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_events', function (Blueprint $table) {
            $table->string('visitor_id', 64)->nullable()->after('session_id');
            $table->string('traffic_source', 120)->nullable()->after('visitor_id');
            $table->string('traffic_medium', 120)->nullable()->after('traffic_source');
            $table->string('traffic_campaign', 120)->nullable()->after('traffic_medium');
            $table->string('device_category', 16)->nullable()->after('traffic_campaign');
            $table->string('country_code', 2)->nullable()->after('device_category');

            $table->index(['visitor_id', 'occurred_at'], 'access_events_visitor_occurred_index');
            $table->index(['traffic_source', 'traffic_medium', 'occurred_at'], 'access_events_traffic_occurred_index');
            $table->index(['device_category', 'occurred_at'], 'access_events_device_occurred_index');
            $table->index(['country_code', 'occurred_at'], 'access_events_country_occurred_index');
        });
    }

    public function down(): void
    {
        Schema::table('access_events', function (Blueprint $table) {
            $table->dropIndex('access_events_visitor_occurred_index');
            $table->dropIndex('access_events_traffic_occurred_index');
            $table->dropIndex('access_events_device_occurred_index');
            $table->dropIndex('access_events_country_occurred_index');
            $table->dropColumn([
                'visitor_id',
                'traffic_source',
                'traffic_medium',
                'traffic_campaign',
                'device_category',
                'country_code',
            ]);
        });
    }
};
