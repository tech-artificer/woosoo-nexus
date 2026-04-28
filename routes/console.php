<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pulse:check')->everyMinute();
// Schedule::command('horizon:snapshot')->everyFiveMinutes(); // Horizon not installed

// REMOVED 2026-04-07: ProcessOrderLogs schedule disabled for production hardening.
// The job depends on `order_update_logs`, which does not exist in production DB.
// Do NOT re-enable periodic dispatch without a migration + queue architecture plan.
// Schedule::job(new \App\Jobs\ProcessOrderLogs)->everyFiveSeconds();

// Task 2.3 (Mission-8): Re-broadcast unacknowledged print events after 2-min stall.
// retry_count tracks backend broadcast counter (distinct from device-ack 'attempts').
Schedule::job(new \App\Jobs\RetryUnacknowledgedPrintEvents)->everyMinute()->withoutOverlapping();

// Task 2.7 (Mission-8): Log a warning for relay devices that have gone heartbeat-silent.
Schedule::job(new \App\Jobs\CheckStaleRelayHeartbeats)->everyThreeMinutes();

// Daily cleanup: purge acknowledged + dead-letter print events older than 7 days.
Schedule::call(function () {
    // forceDelete: PrintEvent now has SoftDeletes — use forceDelete to actually remove rows.
    $acked = \App\Models\PrintEvent::where('is_acknowledged', true)
        ->where('acknowledged_at', '<', now()->subDays(7))
        ->forceDelete();

    // Task 2.3: Dead-letter events accumulate indefinitely without this purge.
    $deadLettered = \App\Models\PrintEvent::where('backend_status', 'dead_letter')
        ->where('updated_at', '<', now()->subDays(7))
        ->forceDelete();

    \Illuminate\Support\Facades\Log::info('Daily print_events purge', [
        'acknowledged_deleted' => $acked,
        'dead_letter_deleted'  => $deadLettered,
    ]);
})->daily()->at('03:00');

// Task 3.3 (Mission-8): Hard-delete soft-deleted records older than 90 days.
// SoftDeletes is now on device_orders, devices, and print_events.
Schedule::call(function () {
    $orders   = \App\Models\DeviceOrder::onlyTrashed()->where('deleted_at', '<', now()->subDays(90))->forceDelete();
    $devices  = \App\Models\Device::onlyTrashed()->where('deleted_at', '<', now()->subDays(90))->forceDelete();
    $events   = \App\Models\PrintEvent::onlyTrashed()->where('deleted_at', '<', now()->subDays(90))->forceDelete();
    \Illuminate\Support\Facades\Log::info('90-day hard-delete purge', [
        'orders'  => $orders,
        'devices' => $devices,
        'events'  => $events,
    ]);
})->weekly()->sundays()->at('02:00');
