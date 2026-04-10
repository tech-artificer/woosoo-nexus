<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // type identifies the device role: 'tablet', 'printer_relay', etc.
            $table->string('type')->nullable()->default(null)->after('status');
            // last_heartbeat_at is updated by the relay device on each heartbeat ping.
            $table->timestamp('last_heartbeat_at')->nullable()->after('type');

            $table->index(['type', 'last_heartbeat_at'], 'idx_devices_type_heartbeat');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('idx_devices_type_heartbeat');
            $table->dropColumn(['type', 'last_heartbeat_at']);
        });
    }
};
