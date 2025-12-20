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
            // Performance indexes for filters/pagination
            if (!Schema::hasColumn('device_orders', 'branch_id')) {
                // In case older installations are missing branch_id, avoid fatal
                // (Do not add the column here; migration should exist elsewhere.)
            }
            // Add simple indexes (safe on MySQL >= 8; duplicates will error if already present)
            try {
                $table->index('created_at', 'idx_device_orders_created_at');
            } catch (\Throwable $e) {
                // ignore if already exists
            }
            try {
                $table->index('branch_id', 'idx_device_orders_branch_id');
            } catch (\Throwable $e) {
                // ignore if already exists
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_orders', function (Blueprint $table) {
            try { $table->dropIndex('idx_device_orders_created_at'); } catch (\Throwable $e) {}
            try { $table->dropIndex('idx_device_orders_branch_id'); } catch (\Throwable $e) {}
        });
    }
};
