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
            if (!Schema::hasColumn('device_orders', 'printed_by')) {
                $table->string('printed_by', 100)->nullable()->after('printed_at');
            }

            // Add composite index if it doesn't exist
            $idx = 'idx_unprinted_orders';
            if (!Schema::hasColumn('device_orders', $idx)) {
                $table->index(['session_id', 'is_printed', 'status'], $idx);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_orders', function (Blueprint $table) {
            $table->dropIndex('idx_unprinted_orders');
            if (Schema::hasColumn('device_orders', 'printed_by')) {
                $table->dropColumn('printed_by');
            }
        });
    }
};
