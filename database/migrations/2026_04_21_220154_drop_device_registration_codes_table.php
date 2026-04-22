<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 9A — Group D5: Drop device_registration_codes table.
 *
 * GATE CONDITION — Do NOT run this migration until all four conditions are met:
 *   1. No known active client still sending `code` (legacy key) in production traffic.
 *   2. Registration smoke tests pass with `security_code` only across staging and tablet verification.
 *   3. CT-01 and CT-06 alias sunset checklist items are complete and verified.
 *   4. A release note explicitly announces alias removal timing.
 *
 * Rollback: The down() method recreates the table schema so this migration is reversible
 * within the same release window if post-deployment issues are found.
 *
 * Reference: CASE_FILE_PLAN_9A_ALIAS_SUNSET_DECISION_2026-04-21.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('device_registration_codes');
    }

    public function down(): void
    {
        Schema::create('device_registration_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->boolean('used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }
};
