<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Guard: drop any partial tables from a previous failed run before recreating.
        Schema::dropIfExists('tablet_package_allowed_menus');
        Schema::dropIfExists('tablet_package_configs');

        Schema::create('tablet_package_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_price', 10, 2)->default(0);
            $table->unsignedTinyInteger('min_meat')->default(1);
            $table->unsignedTinyInteger('max_meat')->default(3);
            $table->unsignedTinyInteger('min_side')->default(0);
            $table->unsignedTinyInteger('max_side')->default(5);
            $table->unsignedTinyInteger('min_dessert')->default(0);
            $table->unsignedTinyInteger('max_dessert')->default(2);
            $table->unsignedTinyInteger('min_beverage')->default(0);
            $table->unsignedTinyInteger('max_beverage')->default(2);
            $table->unsignedBigInteger('banner_media_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('tablet_package_allowed_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_config_id');
            $table->foreign('package_config_id')
                  ->references('id')->on('tablet_package_configs')
                  ->cascadeOnDelete();
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

            $table->index(['package_config_id', 'menu_type'], 'tpam_config_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablet_package_allowed_menus');
        Schema::dropIfExists('tablet_package_configs');
    }
};
