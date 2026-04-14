<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->timestamp('recorded_at');
            $table->decimal('battery_level', 5, 2)->nullable();
            $table->unsignedBigInteger('memory_used_bytes')->nullable();
            $table->unsignedBigInteger('memory_total_bytes')->nullable();
            $table->unsignedBigInteger('storage_used_bytes')->nullable();
            $table->unsignedBigInteger('storage_total_bytes')->nullable();
            $table->integer('wifi_signal_dbm')->nullable();
            $table->integer('ping_ms')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('device_id')
                  ->references('id')->on('devices')
                  ->cascadeOnDelete();

            $table->index(['device_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_heartbeats');
    }
};
