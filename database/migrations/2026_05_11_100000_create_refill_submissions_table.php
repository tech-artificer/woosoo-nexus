<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Compatibility no-op.
     *
     * The canonical refill_submissions table is created by
     * 2026_05_11_030000_create_refill_submissions_table.php.
     * This later duplicate migration previously tried to create the table again
     * with a conflicting schema, which breaks environments whose migration
     * history drifted across branches.
     */
    public function up(): void
    {
        return;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op. The earlier canonical migration owns the table lifecycle.
    }
};
