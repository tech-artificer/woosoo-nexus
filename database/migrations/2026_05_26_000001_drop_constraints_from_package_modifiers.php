<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (test runtime) cannot drop FK/unique by name. The constraints
        // exist on the SQLite schema but tests don't exercise them, so a
        // MySQL-only relaxation is the right scope. Mirrors the pattern in
        // 2025_12_09_110204_remove_session_foreign_key_from_order_update_logs.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('package_modifiers', function (Blueprint $table) {
            $table->dropForeign('package_modifiers_package_id_foreign');
            $table->dropUnique('package_modifiers_package_id_krypton_menu_id_unique');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('package_modifiers', function (Blueprint $table) {
            $table->unique(['package_id', 'krypton_menu_id']);
            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
        });
    }
};
