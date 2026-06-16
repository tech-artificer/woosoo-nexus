<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A package now configures meats only; banchan/sides/desserts/beverages
        // are global (served via Tablet Categories), so the per-package non-meat
        // limits are removed.
        //
        // The "exactly one most popular package" invariant is enforced at the
        // application layer (PackageController::makeOnlyMostPopular(), run inside a
        // DB transaction on create/update/setMostPopular), not by a DB constraint:
        // the target POS database is MySQL, which does not support partial/filtered
        // unique indexes, so a "single true row" constraint isn't expressible here.
        Schema::table('packages', function (Blueprint $table): void {
            $table->boolean('is_most_popular')->default(false)->after('is_active');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn([
                'min_side', 'max_side',
                'min_dessert', 'max_dessert',
                'min_beverage', 'max_beverage',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn('is_most_popular');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->unsignedTinyInteger('min_side')->default(0)->after('max_meat');
            $table->unsignedTinyInteger('max_side')->default(5)->after('min_side');
            $table->unsignedTinyInteger('min_dessert')->default(0)->after('max_side');
            $table->unsignedTinyInteger('max_dessert')->default(2)->after('min_dessert');
            $table->unsignedTinyInteger('min_beverage')->default(0)->after('max_dessert');
            $table->unsignedTinyInteger('max_beverage')->default(2)->after('min_beverage');
        });
    }
};
