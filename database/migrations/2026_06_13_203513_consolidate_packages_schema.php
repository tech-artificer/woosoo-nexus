<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('package_modifiers');

        Schema::table('packages', function (Blueprint $table): void {
            $table->decimal('base_price', 10, 2)->default(0)->after('description');
            $table->unsignedTinyInteger('min_meat')->default(1)->after('base_price');
            $table->unsignedTinyInteger('max_meat')->default(3)->after('min_meat');
            $table->unsignedTinyInteger('min_side')->default(0)->after('max_meat');
            $table->unsignedTinyInteger('max_side')->default(5)->after('min_side');
            $table->unsignedTinyInteger('min_dessert')->default(0)->after('max_side');
            $table->unsignedTinyInteger('max_dessert')->default(2)->after('min_dessert');
            $table->unsignedTinyInteger('min_beverage')->default(0)->after('max_dessert');
            $table->unsignedTinyInteger('max_beverage')->default(2)->after('min_beverage');
            $table->unsignedBigInteger('banner_media_id')->nullable()->after('max_beverage');
            $table->unsignedBigInteger('krypton_menu_id')->nullable()->change();
        });

        Schema::create('package_allowed_menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->unsignedBigInteger('krypton_menu_id');
            $table->string('menu_type', 20)->default('meat');
            $table->string('meat_category_code', 50)->nullable();
            $table->decimal('extra_price', 10, 2)->default(0);
            $table->unsignedTinyInteger('quantity_limit')->default(1);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['package_id', 'menu_type'], 'pam_package_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_allowed_menus');

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn([
                'base_price', 'min_meat', 'max_meat', 'min_side', 'max_side',
                'min_dessert', 'max_dessert', 'min_beverage', 'max_beverage', 'banner_media_id',
            ]);
            $table->unsignedBigInteger('krypton_menu_id')->nullable(false)->change();
        });

        Schema::create('package_modifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('krypton_menu_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['package_id', 'sort_order']);
        });
    }
};
