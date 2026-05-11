<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropUnique(['krypton_menu_id']);
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unique('krypton_menu_id');
        });
    }
};
