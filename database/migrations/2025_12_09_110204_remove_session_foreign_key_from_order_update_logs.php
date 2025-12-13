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
        // Only attempt to modify the table on MySQL and if the table exists.
        if (Schema::hasTable('order_update_logs') && DB::connection()->getDriverName() === 'mysql') {
            // Get all foreign keys on the order_update_logs table for column session_id
            $foreignKeys = DB::select(<<<'SQL'
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'order_update_logs'
                  AND COLUMN_NAME = 'session_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            SQL
            );

            if (!empty($foreignKeys)) {
                foreach ($foreignKeys as $fk) {
                    try {
                        DB::statement("ALTER TABLE order_update_logs DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                        \Log::info("Dropped foreign key: {$fk->CONSTRAINT_NAME}");
                    } catch (\Exception $e) {
                        \Log::error("Failed to drop foreign key {$fk->CONSTRAINT_NAME}: " . $e->getMessage());
                    }
                }
            }

            // Make session_id nullable
            Schema::table('order_update_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('session_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible - we don't want to restore the problematic constraint
    }
};
