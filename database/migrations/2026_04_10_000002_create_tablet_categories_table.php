<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tablet_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 100)->nullable();
            $table->string('color', 20)->nullable()->default('#3B82F6');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('tablet_category_menu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablet_category_id')->constrained('tablet_categories')->cascadeOnDelete();
            $table->unsignedBigInteger('krypton_menu_id');
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tablet_category_id', 'krypton_menu_id']);
            $table->index(['tablet_category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablet_category_menu');
        Schema::dropIfExists('tablet_categories');
    }
};
