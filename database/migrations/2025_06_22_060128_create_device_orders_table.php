<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\OrderStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('table_id');
            $table->string('order_id')->nullable();
            $table->string('order_number')->nullable();
            $table->string('status')->default(OrderStatus::PENDING);
            $table->json('items');
            $table->json('meta'); //total, tax, discount, notes
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_orders');
    }
};
