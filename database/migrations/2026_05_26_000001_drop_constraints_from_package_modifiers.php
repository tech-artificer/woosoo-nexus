<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_modifiers', function (Blueprint $table) {
            $table->dropForeign('package_modifiers_package_id_foreign');
            $table->dropUnique('package_modifiers_package_id_krypton_menu_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('package_modifiers', function (Blueprint $table) {
            $table->unique(['package_id', 'krypton_menu_id']);
            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
        });
    }
};
