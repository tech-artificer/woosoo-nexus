<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 3.3 (Mission-8): Add soft-delete support to print_events.
 *
 * device_orders.deleted_at and devices.deleted_at already exist in the schema.
 * Only print_events needs the column added here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            $table->softDeletes(); // adds nullable deleted_at timestamp
        });
    }

    public function down(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
