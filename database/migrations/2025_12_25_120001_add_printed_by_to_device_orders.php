<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('device_orders') && ! Schema::hasColumn('device_orders', 'printed_by')) {
            Schema::table('device_orders', function (Blueprint $table) {
                $table->string('printed_by')->nullable()->after('printed_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('device_orders') && Schema::hasColumn('device_orders', 'printed_by')) {
            Schema::table('device_orders', function (Blueprint $table) {
                $table->dropColumn('printed_by');
            });
        }
    }
};
