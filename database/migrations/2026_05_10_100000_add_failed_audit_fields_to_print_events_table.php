<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            $table->unsignedInteger('attempt_count')->nullable();
            $table->timestamp('failed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('print_events', function (Blueprint $table) {
            $table->dropColumn(['attempt_count', 'failed_at']);
        });
    }
};
