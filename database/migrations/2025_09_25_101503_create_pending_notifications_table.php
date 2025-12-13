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
        if(!Schema::hasTable('pending_notifications')) {
            Schema::create('pending_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('message_id')->unique();
            $table->string('channel');
            $table->string('event');
            $table->json('payload');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('delivered')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['device_id', 'delivered']);
            $table->index(['expires_at']);
            $table->foreign('device_id')->references('id')->on('devices');
        });
        }
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_notifications');
    }
};
