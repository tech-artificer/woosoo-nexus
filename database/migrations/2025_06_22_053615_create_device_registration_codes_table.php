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
        Schema::create('device_registration_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6)->unique();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_registration_code');
    }
};
