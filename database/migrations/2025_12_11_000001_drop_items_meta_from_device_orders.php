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
            if (Schema::hasColumn('device_orders', 'items')) {
                $table->dropColumn('items');
            }
            if (Schema::hasColumn('device_orders', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('device_orders', 'items')) {
                $table->json('items')->nullable();
            }
            if (! Schema::hasColumn('device_orders', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }
};
