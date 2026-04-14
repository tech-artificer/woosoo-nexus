<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('device_order_items') && ! Schema::hasColumn('device_order_items', 'is_refill')) {
            Schema::table('device_order_items', function (Blueprint $table) {
                $table->boolean('is_refill')->default(false)->after('status');
            });
        }

        if (Schema::hasTable('device_order_items') && Schema::hasColumn('device_order_items', 'is_refill')) {
            // Backfill refills that were mirrored from POS ordered_menu rows.
            if (Schema::hasColumn('device_order_items', 'ordered_menu_id') && Schema::hasColumn('device_order_items', 'menu_id')) {
                DB::table('device_order_items')
                    ->whereNotNull('ordered_menu_id')
                    ->whereColumn('ordered_menu_id', '!=', 'menu_id')
                    ->update(['is_refill' => true]);
            }

            // Fallback: mark items with notes containing 'refill' (case-insensitive in MySQL).
            if (Schema::hasColumn('device_order_items', 'notes')) {
                DB::table('device_order_items')
                    ->where('notes', 'like', '%refill%')
                    ->update(['is_refill' => true]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('device_order_items') && Schema::hasColumn('device_order_items', 'is_refill')) {
            Schema::table('device_order_items', function (Blueprint $table) {
                $table->dropColumn('is_refill');
            });
        }
    }
};
