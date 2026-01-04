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
        Schema::create('broadcast_events', function (Blueprint $table) {
            $table->id();
            $table->string('channel'); // e.g., 'admin.print', 'device.123'
            $table->string('event'); // e.g., 'PrintOrder', 'OrderUpdate'
            $table->longText('payload'); // JSON payload
            $table->timestamp('created_at')->useCurrent();
            $table->index('channel');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_events');
    }
};
