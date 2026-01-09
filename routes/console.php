<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:process-logs')->everyFiveSeconds();

// Purge acknowledged print_events daily (they're transient; device_order.is_printed is source of truth)
Schedule::call(function () {
    $deleted = \App\Models\PrintEvent::where('is_acknowledged', true)
        ->where('acknowledged_at', '<', now()->subDay())
        ->delete();
    \Illuminate\Support\Facades\Log::info('Scheduled purge of acknowledged print_events', ['count' => $deleted]);
})->daily()->at('03:00');