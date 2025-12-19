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
        if (Schema::hasTable('device_orders') && !Schema::hasColumn('device_orders', 'is_printed')) {
            Schema::table('device_orders', function (Blueprint $table) {
                $table->boolean('is_printed')->default(false)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('device_orders') && Schema::hasColumn('device_orders', 'is_printed')) {
            Schema::table('device_orders', function (Blueprint $table) {
                $table->dropColumn('is_printed');
            });
        }
    }
};
