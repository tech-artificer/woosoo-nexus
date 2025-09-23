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
        // TODO: Implement the code for adding the 'pin' column to the 'users' table
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'pin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('pin', 6)->nullable();
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
