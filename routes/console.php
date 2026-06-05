<?php

use App\Jobs\CheckStaleRelayHeartbeats;
use App\Jobs\RetryUnacknowledgedPrintEvents;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\PrintEvent;
use App\Models\RefillSubmission;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// runInBackground() spawns a fresh OS process per invocation — prevents memory
// accumulation inside the long-lived schedule:work loop (Pulse aggregation can
// spike 60-100MB; this keeps that cost isolated and disposable).
Schedule::command('pulse:check')->everyMinute()->withoutOverlapping()->runInBackground();
// Schedule::command('horizon:snapshot')->everyFiveMinutes(); // Horizon not installed

// Split-DB safe payment status reconciliation (POS -> local device_orders).
// Replaces dependency on cross-server MySQL trigger updates.
Schedule::command('pos:consume-payment-status-events')
    ->everyFiveSeconds()
    ->withoutOverlapping(3)
    ->runInBackground();

Schedule::command('pos:sync-payment-statuses')
    ->everyMinute()
    ->withoutOverlapping();

// NEX-CASE-013: drain POS-local order-detail outbox; same cadence as the
// payment consumer because POS detail edits (guest_count, totals via
// order_checks) must reach the tablet within seconds to stay non-stale.
Schedule::command('pos:consume-order-detail-events')
    ->everyFiveSeconds()
    ->withoutOverlapping(3)
    ->runInBackground();

// REMOVED 2026-04-07: ProcessOrderLogs schedule disabled for production hardening.
// The job depends on `order_update_logs`, which does not exist in production DB.
// Do NOT re-enable periodic dispatch without a migration + queue architecture plan.
// Schedule::job(new \App\Jobs\ProcessOrderLogs)->everyFiveSeconds();

// Task 2.3 (Mission-8): Re-broadcast unacknowledged print events after 2-min stall.
// retry_count tracks backend broadcast counter (distinct from device-ack 'attempts').
Schedule::job(new RetryUnacknowledgedPrintEvents)->everyMinute()->withoutOverlapping();

// Task 2.7 (Mission-8): Log a warning for relay devices that have gone heartbeat-silent.
Schedule::job(new CheckStaleRelayHeartbeats)
    ->everyThreeMinutes()
    ->withoutOverlapping(10);

// Release refill submissions stuck in a non-terminal "processing" state for more
// than 5 minutes. Without this, a crashed worker mid-refill leaves the row holding
// the idempotency lock, blocking every future retry from the same tablet.
// Mirrors RefillSubmission::isLockExpired() default timeout (300s).
Schedule::call(function () {
    $cutoff = now()->subSeconds(300);
    $stuck = RefillSubmission::whereIn('status', RefillSubmission::PROCESSING_STATES)
        ->where(function ($q) use ($cutoff) {
            $q->where('processing_started_at', '<', $cutoff)
              ->orWhereNull('processing_started_at');
        })
        ->get();

    if ($stuck->isEmpty()) {
        return;
    }

    foreach ($stuck as $row) {
        try {
            $row->markAsFailed('Released by stale-lock sweeper after 5 min in ' . $row->status);
        } catch (\Throwable $e) {
            Log::warning('[RefillSubmission] Sweeper failed to release row', [
                'id' => $row->id,
                'status' => $row->status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    Log::warning('[RefillSubmission] Released stuck rows', ['count' => $stuck->count()]);
})->name('refill-submission-sweeper')->everyMinute()->withoutOverlapping();

// Container-aware scheduler memory monitoring.
// Emits warnings when RSS/peak usage crosses configured thresholds.
Schedule::call(function () {
    $usageMb = (int) ceil(memory_get_usage(true) / 1024 / 1024);
    $peakMb = (int) ceil(memory_get_peak_usage(true) / 1024 / 1024);
    $warnThresholdMb = (int) env('SCHEDULER_MEMORY_WARN_MB', 192);

    if ($usageMb >= $warnThresholdMb || $peakMb >= $warnThresholdMb) {
        Log::warning('[Scheduler] High memory usage detected', [
            'usage_mb' => $usageMb,
            'peak_mb' => $peakMb,
            'warn_threshold_mb' => $warnThresholdMb,
        ]);
    }
})->name('scheduler-memory-monitor')->everyFiveMinutes()->withoutOverlapping();

// Daily cleanup: purge acknowledged + dead-letter print events older than 7 days.
Schedule::call(function () {
    // forceDelete: PrintEvent now has SoftDeletes — use forceDelete to actually remove rows.
    $acked = PrintEvent::where('is_acknowledged', true)
        ->where('acknowledged_at', '<', now()->subDays(7))
        ->forceDelete();

    // Task 2.3: Dead-letter events accumulate indefinitely without this purge.
    $deadLettered = PrintEvent::where('backend_status', 'dead_letter')
        ->where('updated_at', '<', now()->subDays(7))
        ->forceDelete();

    Log::info('Daily print_events purge', [
        'acknowledged_deleted' => $acked,
        'dead_letter_deleted' => $deadLettered,
    ]);
})->daily()->at('03:00');

// Task 3.3 (Mission-8): Hard-delete soft-deleted records older than 90 days.
// SoftDeletes is now on device_orders, devices, and print_events.
Schedule::call(function () {
    $orders = DeviceOrder::onlyTrashed()->where('deleted_at', '<', now()->subDays(90))->forceDelete();
    $devices = Device::onlyTrashed()->where('deleted_at', '<', now()->subDays(90))->forceDelete();
    $events = PrintEvent::onlyTrashed()->where('deleted_at', '<', now()->subDays(90))->forceDelete();
    Log::info('90-day hard-delete purge', [
        'orders' => $orders,
        'devices' => $devices,
        'events' => $events,
    ]);
})->weekly()->sundays()->at('02:00');
