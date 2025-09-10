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
        // Add column only if it doesn't exist
        if (!Schema::hasColumn('device_orders', 'terminal_session_id')) {
          Schema::table('device_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('terminal_session_id');
          });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
