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

        // TODO: make sure the table does not already exist
        if (!Schema::hasTable('device_order_items')) {
            Schema::create('device_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('menu_id');
                $table->integer('quantity')->default(1);
                $table->decimal('price', 10, 4)->default(0);
                $table->decimal('subtotal', 10, 4)->default(0);
                $table->decimal('tax', 10, 4)->default(0);
                $table->decimal('discount', 10, 4)->default(0);
                $table->decimal('total', 10, 4)->default(0);
                $table->string('notes')->nullable();
                $table->softDeletes();
                $table->timestamps();


                $table->foreign('order_id')->references('id')->on('device_orders')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_order_items');
    }
};
