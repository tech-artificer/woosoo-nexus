<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 3.2 (Mission-8): Add performance indexes for hot query paths.
 * All index names use the `idx_` prefix for instant identification in EXPLAIN output.
 *
 * Uses try/catch per index so re-runs and SQLite test environments are safe.
 * Note: devices.token does not exist — Sanctum tokens are in personal_access_tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip index creation on SQLite (test environments) — SQLite auto-indexes efficiently
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // device_orders — each index in its own Schema::table() so try/catch is effective
        // (Laravel batches commands per closure into one ALTER TABLE; catching at the PHP
        //  Blueprint level doesn't intercept MySQL 1061 Duplicate Key errors)
        try {
            Schema::table('device_orders', fn (Blueprint $t) => $t->index('device_id', 'idx_device_orders_device_id'));
        } catch (\Throwable $e) {}

        try {
            Schema::table('device_orders', fn (Blueprint $t) => $t->index('status', 'idx_device_orders_status'));
        } catch (\Throwable $e) {}

        // created_at may already exist from 2025_12_19_010000_add_indexes_on_device_orders
        try {
            Schema::table('device_orders', fn (Blueprint $t) => $t->index('created_at', 'idx_device_orders_created_at'));
        } catch (\Throwable $e) {}

        // print_events — queried every scheduler tick by RetryUnacknowledgedPrintEvents
        try {
            Schema::table('print_events', fn (Blueprint $t) => $t->index('backend_status', 'idx_print_events_status'));
        } catch (\Throwable $e) {}

        try {
            Schema::table('print_events', fn (Blueprint $t) => $t->index('device_order_id', 'idx_print_events_order'));
        } catch (\Throwable $e) {}

        // personal_access_tokens — resolved on every device bearer-token request (Sanctum)
        try {
            Schema::table('personal_access_tokens', fn (Blueprint $t) => $t->index(['tokenable_type', 'tokenable_id'], 'idx_pat_tokenable'));
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // Each drop is a separate Schema::table() so failures are isolated
        foreach (['idx_device_orders_device_id', 'idx_device_orders_status', 'idx_device_orders_created_at'] as $idx) {
            try { Schema::table('device_orders', fn (Blueprint $t) => $t->dropIndex($idx)); } catch (\Throwable $e) {}
        }
        foreach (['idx_print_events_status', 'idx_print_events_order'] as $idx) {
            try { Schema::table('print_events', fn (Blueprint $t) => $t->dropIndex($idx)); } catch (\Throwable $e) {}
        }
        try { Schema::table('personal_access_tokens', fn (Blueprint $t) => $t->dropIndex('idx_pat_tokenable')); } catch (\Throwable $e) {}
    }
};
