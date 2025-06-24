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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid')->unique();
            $table->unsignedBigInteger('branch_id');
            $table->string('name')->unique();
            $table->string('table_id')->nullable(); // FK to tables from POS database
            $table->string('ip_address')->nullable();
            $table->string('port')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('app_version')->nullable(); // version of the app running on the device
            $table->string('last_ip_address')->nullable(); // last known IP address of the device
            $table->timestamp('last_seen_at')->nullable(); // last time the device was active
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
