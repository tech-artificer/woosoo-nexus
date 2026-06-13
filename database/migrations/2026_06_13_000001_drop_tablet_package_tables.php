<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tablet_package_allowed_menus');
        Schema::dropIfExists('tablet_package_configs');
    }

    public function down(): void
    {
        // Intentionally empty — TabletPackageConfig system removed by design.
    }
};
