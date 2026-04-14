<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('security_code', 32)->nullable()->after('last_heartbeat_at');
            $table->timestamp('security_code_generated_at')->nullable()->after('security_code');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn(['security_code', 'security_code_generated_at']);
        });
    }
};
