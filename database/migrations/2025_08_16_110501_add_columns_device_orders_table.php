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
        if (!Schema::hasColumns('device_orders', ['total', 'tax', 'subtotal', 'guest_count', 'discount', 'notes'])) {
            Schema::table('device_orders', function (Blueprint $table) {
                $table->decimal('total', 10, 4)->default(0);
                $table->decimal('tax', 10, 4)->default(0);
                $table->decimal('subtotal', 10, 4)->default(0);
                $table->integer('guest_count')->default(1);
                $table->decimal('discount', 10, 4)->default(0);
                $table->string('notes')->nullable();
            });
        }
    }
};
