<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            // backend_status tracks the backend broadcast lifecycle, separate from device-ack.
            // Values: pending, broadcast, acked, failed, dead_letter
            // NOTE: 'attempts' column is the device-ack counter (existing) — do NOT conflate with retry_count.
            $table->string('backend_status')->default('pending')->after('is_acknowledged');
            $table->timestamp('broadcast_at')->nullable()->after('backend_status');
            // retry_count counts how many times the BACKEND has re-broadcast the event.
            $table->unsignedSmallInteger('retry_count')->default(0)->after('broadcast_at');

            $table->index(['backend_status', 'broadcast_at'], 'idx_print_events_backend_retry');
        });
    }

    public function down(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            $table->dropIndex('idx_print_events_backend_retry');
            $table->dropColumn(['backend_status', 'broadcast_at', 'retry_count']);
        });
    }
};
