<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('filename');
            $table->string('disk', 50)->default('public');
            $table->string('path');
            $table->string('url');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('collection', 50)->nullable()->default('default');
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->index('collection');
            $table->index('created_at');
        });

        Schema::table('menu_images', function (Blueprint $table) {
            $table->unsignedBigInteger('media_file_id')->nullable()->after('id');
            $table->string('image_type', 20)->nullable()->default('gallery')->after('media_file_id');
            $table->unsignedSmallInteger('sort_order')->nullable()->default(0)->after('image_type');

            $table->foreign('media_file_id')
                  ->references('id')->on('media_files')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menu_images', function (Blueprint $table) {
            $table->dropForeign(['media_file_id']);
            $table->dropColumn(['media_file_id', 'image_type', 'sort_order']);
        });
        Schema::dropIfExists('media_files');
    }
};
