<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_requests')) {
            return;
        }

        Schema::table('service_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('service_requests', 'status')) {
                $table->string('status')->default('pending')->after('order_id');
            }

            if (! Schema::hasColumn('service_requests', 'priority')) {
                $table->string('priority')->default('medium')->after('status');
            }

            if (! Schema::hasColumn('service_requests', 'description')) {
                $table->text('description')->nullable()->after('priority');
            }

            if (! Schema::hasColumn('service_requests', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('description');
            }

            if (! Schema::hasColumn('service_requests', 'acknowledged_by')) {
                $table->unsignedBigInteger('acknowledged_by')->nullable()->after('acknowledged_at');
            }

            if (! Schema::hasColumn('service_requests', 'completed_by')) {
                $table->unsignedBigInteger('completed_by')->nullable()->after('acknowledged_by');
            }

            if (! Schema::hasColumn('service_requests', 'assigned_device_id')) {
                $table->unsignedBigInteger('assigned_device_id')->nullable()->after('completed_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_requests')) {
            return;
        }

        Schema::table('service_requests', function (Blueprint $table) {
            foreach (['assigned_device_id', 'completed_by', 'acknowledged_by', 'acknowledged_at', 'description', 'priority', 'status'] as $column) {
                if (Schema::hasColumn('service_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};