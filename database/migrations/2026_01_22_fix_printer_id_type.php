<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * CRITICAL FIX: printer_id column must be string to store Bluetooth MAC addresses.
     * Original migration created it as bigint unsigned, which causes data truncation.
     */
    public function up(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            // Change printer_id from bigint to varchar(100) to support MAC addresses
            $table->string('printer_id', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            // Revert to bigint unsigned (original type)
            $table->unsignedBigInteger('printer_id')->nullable()->change();
        });
    }
};
