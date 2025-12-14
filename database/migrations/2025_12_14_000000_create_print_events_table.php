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
        Schema::create('print_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_order_id')->nullable();
            $table->unsignedBigInteger('printer_id')->nullable();
            $table->string('event_type');
            $table->json('meta')->nullable();
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('device_order_id')->references('id')->on('device_orders')->nullOnDelete();
            $table->index(['device_order_id', 'event_type']);
            $table->index('is_acknowledged');
            $table->index('printer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_events');
    }
};
