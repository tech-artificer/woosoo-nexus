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
        Schema::table('service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('service_requests', 'device_order_id')) {
               $table->unsignedBigInteger('device_order_id');
            }
            if (!Schema::hasColumn('service_requests', 'table_service_id')) {
                $table->unsignedBigInteger('table_service_id');
            }
            if (!Schema::hasColumn('service_requests', 'order_id')) {
                $table->unsignedBigInteger('order_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('service_requests')) {
            Schema::table('service_requests', function (Blueprint $table) {
                $columns = ['device_order_id', 'table_service_id', 'order_id'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('service_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
