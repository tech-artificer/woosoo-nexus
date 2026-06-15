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
        // The TabletPackageConfig system was removed by design; the dropped tables
        // cannot be reconstructed. Fail loudly so a rollback never reports success
        // while the schema is actually gone.
        throw new \RuntimeException('Irreversible migration: tablet_package_* tables were dropped by design and cannot be restored.');
    }
};
