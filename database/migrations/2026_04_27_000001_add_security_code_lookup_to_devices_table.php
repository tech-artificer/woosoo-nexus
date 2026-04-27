<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('security_code_lookup', 64)
                ->nullable()
                ->after('security_code');

            $table->unique('security_code_lookup', 'devices_security_code_lookup_unique');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropUnique('devices_security_code_lookup_unique');
            $table->dropColumn('security_code_lookup');
        });
    }
};
