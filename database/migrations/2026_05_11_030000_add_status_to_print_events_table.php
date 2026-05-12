<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('print_events')) {
            return;
        }

        Schema::table('print_events', function (Blueprint $table) {
            if (!Schema::hasColumn('print_events', 'status')) {
                $table->enum('status', ['pending', 'reserved', 'printing', 'printed', 'failed'])
                    ->default('pending')
                    ->after('event_type')
                    ->comment('Print job state machine: pending→reserved→printing→printed / failed→pending');
            }

            if (!Schema::hasColumn('print_events', 'reserved_by_device_id')) {
                $table->string('reserved_by_device_id')->nullable()->after('status')
                    ->comment('Device ID that reserved this job (prevents multi-bridge collisions)');
            }

            if (!Schema::hasColumn('print_events', 'reserved_at')) {
                $table->timestamp('reserved_at')->nullable()->after('reserved_by_device_id');
            }
        });

        try {
            Schema::table('print_events', function (Blueprint $table) {
                $table->index('status', 'idx_print_events_job_status');
            });
        } catch (\Throwable $e) {
        }

        // Back-fill: existing acknowledged events → printed, everything else → pending
        if (Schema::hasColumn('print_events', 'status')) {
            DB::statement("
                UPDATE print_events
                SET status = CASE
                    WHEN is_acknowledged = 1 THEN 'printed'
                    WHEN backend_status = 'failed' THEN 'failed'
                    ELSE 'pending'
                END
                WHERE deleted_at IS NULL
            ");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('print_events')) {
            return;
        }

        Schema::table('print_events', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_print_events_job_status');
            } catch (\Throwable $e) {
            }

            $columnsToDrop = [];

            foreach (['status', 'reserved_by_device_id', 'reserved_at'] as $column) {
                if (Schema::hasColumn('print_events', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
