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
        if(!Schema::hasTable('message_acknowledgments')) {
            Schema::create('message_acknowledgments', function (Blueprint $table) {
                $table->id();
                $table->string('message_id')->unique();
                $table->unsignedBigInteger('device_id');
                $table->timestamp('acknowledged_at');
                $table->string('client_info')->nullable();
                $table->timestamps();
                
                $table->foreign('device_id')->references('id')->on('devices');
            });
        }

       
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_acknowledgments');
    }
};
