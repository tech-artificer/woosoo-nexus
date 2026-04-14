<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_orders', function (Blueprint $table) {
            $table->uuid('order_uuid')->nullable()->after('order_number');
            $table->unique('order_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('device_orders', function (Blueprint $table) {
            $table->dropUnique(['order_uuid']);
            $table->dropColumn('order_uuid');
        });
    }
};
