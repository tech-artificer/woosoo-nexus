<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * B2 Device Identity: Add relay device acknowledgment tracking
     * and clarify printer_id semantics (physical printer vs relay device)
     */
    public function up(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            // Track which relay device acknowledged this print event
            $table->unsignedBigInteger('acknowledged_by_device_id')
                  ->nullable()
                  ->after('acknowledged_at')
                  ->comment('FK to devices.id - which relay device sent the ack');
            
            $table->foreign('acknowledged_by_device_id')
                  ->references('id')
                  ->on('devices')
                  ->nullOnDelete();

            // Add printer name for human-readable audit trail
            $table->string('printer_name', 100)
                  ->nullable()
                  ->after('printer_id')
                  ->comment('Human-readable printer name (e.g., Kitchen Printer)');

            // Index for querying by relay device (audit queries)
            $table->index('acknowledged_by_device_id', 'idx_print_events_acked_by_device');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            $table->dropForeign(['acknowledged_by_device_id']);
            $table->dropIndex('idx_print_events_acked_by_device');
            $table->dropColumn([
                'acknowledged_by_device_id',
                'printer_name',
            ]);
        });
    }
};
