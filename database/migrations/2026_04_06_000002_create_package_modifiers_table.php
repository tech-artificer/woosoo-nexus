<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            // References Krypton POS menus.id — cross-DB, no FK constraint.
            $table->unsignedBigInteger('krypton_menu_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['package_id', 'sort_order']);
            // A modifier menu can only appear once per package.
            $table->unique(['package_id', 'krypton_menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_modifiers');
    }
};
