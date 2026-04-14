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
        Schema::table('device_orders', function (Blueprint $table) {
            $table->index('session_id');
            $table->index('order_id');
            $table->index('status');
        });

        Schema::table('device_order_items', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('print_events', function (Blueprint $table) {
            $table->index('device_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_orders', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->dropIndex(['order_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('device_order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('print_events', function (Blueprint $table) {
            $table->dropIndex(['device_order_id']);
        });
    }
};
