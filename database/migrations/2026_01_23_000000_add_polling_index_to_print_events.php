<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add composite index for optimized polling queries on print_events table.
     * This index targets the query pattern: WHERE is_acknowledged = 0 AND created_at > ?
     *
     * Performance Impact:
     * - Eliminates full table scans on polling endpoints
     * - Optimizes relay device event retrieval
     * - Critical for production scale (>10k print events)
     */
    public function up(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            // Composite index for polling queries: unacknowledged events sorted by creation time
            // Index order matters: is_acknowledged (equality filter) → created_at (range filter + sort)
            $table->index(['is_acknowledged', 'created_at'], 'idx_print_events_polling');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            $table->dropIndex('idx_print_events_polling');
        });
    }
};
