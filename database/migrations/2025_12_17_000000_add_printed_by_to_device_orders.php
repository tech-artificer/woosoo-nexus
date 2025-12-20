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
        if (! Schema::hasTable('device_orders')) {
            return;
        }

        Schema::table('device_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('device_orders', 'printed_at')) {
                $table->timestamp('printed_at')->nullable()->after('is_printed');
            }

            if (! Schema::hasColumn('device_orders', 'printed_by')) {
                $table->string('printed_by', 100)->nullable()->after('printed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('device_orders')) {
            return;
        }

        Schema::table('device_orders', function (Blueprint $table) {
            if (Schema::hasColumn('device_orders', 'printed_by')) {
                $table->dropColumn('printed_by');
            }

            // Do not drop printed_at if it existed previously; only drop if added by this migration
            // For safety, we will not drop printed_at here to avoid removing existing audit data.
        });
    }
};
