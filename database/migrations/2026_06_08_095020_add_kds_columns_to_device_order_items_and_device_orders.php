<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_order_items', function (Blueprint $table) {
            $table->boolean('done')->default(false)->after('is_refill');
            $table->timestamp('done_at')->nullable()->after('done');
        });

        Schema::table('device_orders', function (Blueprint $table) {
            $table->smallInteger('recalled')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('device_order_items', function (Blueprint $table) {
            $table->dropColumn(['done', 'done_at']);
        });

        Schema::table('device_orders', function (Blueprint $table) {
            $table->dropColumn('recalled');
        });
    }
};
