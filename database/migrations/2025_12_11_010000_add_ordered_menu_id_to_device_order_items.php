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
        if (Schema::hasTable('device_order_items') && ! Schema::hasColumn('device_order_items', 'ordered_menu_id')) {
            Schema::table('device_order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('ordered_menu_id')->nullable()->after('order_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('device_order_items') && Schema::hasColumn('device_order_items', 'ordered_menu_id')) {
            Schema::table('device_order_items', function (Blueprint $table) {
                $table->dropColumn('ordered_menu_id');
            });
        }
    }
};
