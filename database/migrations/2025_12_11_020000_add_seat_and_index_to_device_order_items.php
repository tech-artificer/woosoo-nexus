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
        if (Schema::hasTable('device_order_items')) {
            Schema::table('device_order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('device_order_items', 'seat_number')) {
                    $table->integer('seat_number')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('device_order_items', 'index')) {
                    $table->integer('index')->default(1)->after('seat_number');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('device_order_items')) {
            Schema::table('device_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('device_order_items', 'index')) {
                    $table->dropColumn('index');
                }
                if (Schema::hasColumn('device_order_items', 'seat_number')) {
                    $table->dropColumn('seat_number');
                }
            });
        }
    }
};
