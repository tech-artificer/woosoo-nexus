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
        if (Schema::hasTable('device_orders')) {
            Schema::table('device_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('device_orders', 'items')) {
                    $table->json('items')->nullable()->after('status');
                }
                if (!Schema::hasColumn('device_orders', 'meta')) {
                    $table->json('meta')->nullable()->after('items');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('device_orders')) {
            Schema::table('device_orders', function (Blueprint $table) {
                if (Schema::hasColumn('device_orders', 'meta')) {
                    $table->dropColumn('meta');
                }
                if (Schema::hasColumn('device_orders', 'items')) {
                    $table->dropColumn('items');
                }
            });
        }
    }
};
