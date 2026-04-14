<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 3.1 (Mission-8): Audit log table.
 * Captures order status changes, device registration, session events, and failed auth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 64)->index();           // e.g. 'order.status_changed'
            $table->string('actor_type', 64)->nullable();   // 'device', 'user', 'system'
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('subject_type', 64)->nullable(); // 'DeviceOrder', 'Device', etc.
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();               // diff, IP, request_id, etc.
            $table->string('ip_address', 45)->nullable();   // IPv4 or IPv6
            $table->string('request_id', 36)->nullable();   // correlates to RequestId middleware
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id'], 'idx_audit_subject');
            $table->index(['actor_type', 'actor_id'],     'idx_audit_actor');
            $table->index('created_at',                   'idx_audit_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
