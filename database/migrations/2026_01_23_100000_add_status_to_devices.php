<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add device status tracking for heartbeat monitoring.
     * Enables admin queries for device health: online, printer_connected, queue_pending, queue_failed.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('status', 50)
                ->nullable()
                ->after('is_active')
                ->comment('Device/printer status: online, printer_connected, queue_pending, queue_failed');
            
            // Index for admin queries filtering by status
            $table->index('status', 'idx_devices_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('idx_devices_status');
            $table->dropColumn('status');
        });
    }
};
