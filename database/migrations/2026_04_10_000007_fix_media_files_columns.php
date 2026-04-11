<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            // Rename 'filename' → 'original_filename' to match model/controller
            $table->renameColumn('filename', 'original_filename');

            // Drop the redundant 'original_name' column (was nullable, now superseded)
            $table->dropColumn('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->renameColumn('original_filename', 'filename');
            $table->string('original_name')->nullable()->after('size_bytes');
        });
    }
};
