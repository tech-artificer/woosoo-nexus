<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tablet_categories', function (Blueprint $table) {
            $table->boolean('is_unlimited')->default(false)->after('is_active');
        });

        // Backfill: meats and sides have always been the refill-eligible tabs.
        DB::table('tablet_categories')
            ->whereIn('slug', ['meats', 'sides'])
            ->update(['is_unlimited' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablet_categories', function (Blueprint $table) {
            $table->dropColumn('is_unlimited');
        });
    }
};
