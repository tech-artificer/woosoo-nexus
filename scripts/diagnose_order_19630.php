<?php
/**
 * Diagnostic script for Order 19630 - No Event Fired Issue
 * 
 * Run: php scripts/diagnose_order_19630.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OrderUpdateLog;
use App\Models\DeviceOrder;
use Illuminate\Support\Facades\Cache;

$orderId = 19630;

echo "\n=== 🔍 Diagnosing Order {$orderId} ===\n\n";

// 1. Check DeviceOrder status
echo "1️⃣  Checking DeviceOrder...\n";
$deviceOrder = DeviceOrder::where('order_id', $orderId)->first();
if ($deviceOrder) {
    echo "   ✅ DeviceOrder found\n";
    echo "      - ID: {$deviceOrder->id}\n";
    echo "      - Status: {$deviceOrder->status->value}\n";
    echo "      - Device ID: {$deviceOrder->device_id}\n";
    echo "      - Order Number: {$deviceOrder->order_number}\n";
    echo "      - Updated: {$deviceOrder->updated_at}\n\n";
} else {
    echo "   ❌ DeviceOrder NOT found for order_id={$orderId}\n";
    echo "      → This order doesn't exist in woosoo_nexus database\n\n";
    exit(1);
}

// 2. Check OrderUpdateLog (should be deleted if processed)
echo "2️⃣  Checking OrderUpdateLog...\n";
$log = OrderUpdateLog::where('order_id', $orderId)->first();
if ($log) {
    echo "   ⚠️  OrderUpdateLog STILL EXISTS (NOT PROCESSED!)\n";
    echo "      - ID: {$log->id}\n";
    echo "      - is_processed: " . ($log->is_processed ? '✅ true' : '❌ false') . "\n";
    echo "      - is_open: " . ($log->is_open ? '✅ true' : '❌ false') . "\n";
    echo "      - is_voided: " . ($log->is_voided ? '✅ true' : '❌ false') . "\n";
    echo "      - Created: {$log->created_at}\n";
    echo "      - Updated: {$log->updated_at}\n\n";
    
    echo "   🔴 DIAGNOSIS: Laravel Scheduler is NOT running!\n";
    echo "      → ProcessOrderLogs job never processed this log\n";
    echo "      → No OrderCompleted event was fired\n\n";
    
    $shouldProcess = !$log->is_processed && !$log->is_open;
    if ($shouldProcess) {
        echo "   ℹ️  This log SHOULD be processed (is_processed=false, is_open=false)\n";
        echo "      → Run: Restart-Service woosoo-scheduler\n\n";
    }
} else {
    echo "   ✅ OrderUpdateLog deleted (was processed by scheduler)\n\n";
    echo "   🟢 DIAGNOSIS: Scheduler DID run successfully\n";
    echo "      → ProcessOrderLogs job completed\n";
    echo "      → DeviceOrder status was updated\n";
    echo "      → OrderCompleted event SHOULD have been fired\n\n";
    
    echo "   🔍 Next Steps:\n";
    echo "      1. Check Laravel logs for 'OrderCompleted' broadcast\n";
    echo "      2. Check Reverb logs for WebSocket events\n";
    echo "      3. Check tablet browser console for received events\n";
    echo "      4. Verify Reverb service is running\n\n";
}

// 3. Check for backlog of unprocessed logs
echo "3️⃣  Checking for unprocessed order logs...\n";
$unprocessed = OrderUpdateLog::where('is_processed', false)->where('is_open', false)->count();
$total = OrderUpdateLog::count();

if ($unprocessed > 0) {
    echo "   ⚠️  Found {$unprocessed} unprocessed logs (out of {$total} total)\n";
    echo "      → This is a CRITICAL issue — scheduler is NOT processing logs\n";
    echo "      → Fix: Restart-Service woosoo-scheduler\n\n";
    
    // Show sample of unprocessed logs
    $samples = OrderUpdateLog::where('is_processed', false)
        ->where('is_open', false)
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    echo "   📋 Recent unprocessed logs:\n";
    foreach ($samples as $sample) {
        $age = now()->diffForHumans($sample->created_at);
        echo "      - Order {$sample->order_id} (created {$age})\n";
    }
    echo "\n";
} else {
    echo "   ✅ No unprocessed logs — scheduler is healthy\n\n";
}

// 4. Check scheduler heartbeat
echo "4️⃣  Checking scheduler heartbeat...\n";
$lastRun = Cache::get('scheduler:last_run');
if ($lastRun) {
    $secondsAgo = now()->diffInSeconds($lastRun);
    if ($secondsAgo < 30) {
        echo "   ✅ Scheduler last ran {$secondsAgo} seconds ago\n";
        echo "      → Scheduler is HEALTHY (ran within last 30 seconds)\n\n";
    } else {
        echo "   ⚠️  Scheduler last ran {$secondsAgo} seconds ago\n";
        echo "      → Scheduler may be STUCK or SLOW\n";
        echo "      → Expected: < 30 seconds (scheduled to run every 5 seconds)\n\n";
    }
} else {
    echo "   ❌ No scheduler heartbeat found in cache\n";
    echo "      → Scheduler has NEVER run, OR cache was cleared\n";
    echo "      → Fix: Restart-Service woosoo-scheduler\n\n";
}

// 5. Service status recommendations
echo "5️⃣  Recommended Actions:\n";
echo "\n";
echo "   PowerShell Commands:\n";
echo "   ═══════════════════════════════════════════════════════════\n";
echo "   # Check service status:\n";
echo "   Get-Service woosoo-scheduler, woosoo-queue-worker, woosoo-reverb\n\n";
echo "   # If any are stopped, start them:\n";
echo "   Start-Service woosoo-scheduler\n";
echo "   Start-Service woosoo-queue-worker\n";
echo "   Start-Service woosoo-reverb\n\n";
echo "   # Check recent scheduler logs:\n";
echo "   Get-Content C:\\deployment-manager-legacy\\logs\\scheduler\\scheduler.log -Tail 50\n\n";
echo "   # Check Laravel logs for errors:\n";
echo "   Get-Content C:\\deployment-manager-legacy\\apps\\woosoo-nexus\\storage\\logs\\laravel.log -Tail 50\n";
echo "   ═══════════════════════════════════════════════════════════\n\n";

// 6. Summary
echo "═════════════════════════════════════════════════════════════\n";
echo "📊 SUMMARY\n";
echo "═════════════════════════════════════════════════════════════\n\n";

if ($log) {
    echo "🔴 CRITICAL ISSUE DETECTED\n\n";
    echo "Order {$orderId} completed in database but event NOT fired.\n";
    echo "Root Cause: Laravel Scheduler is NOT processing order logs.\n\n";
    echo "Immediate Fix:\n";
    echo "   powershell> Restart-Service woosoo-scheduler\n\n";
    echo "After restart, the scheduler will:\n";
    echo "   1. Process the pending OrderUpdateLog (ID: {$log->id})\n";
    echo "   2. Fire the OrderCompleted event\n";
    echo "   3. Tablet will receive the event and end the session\n\n";
} else {
    echo "🟢 Scheduler processed the order successfully.\n\n";
    echo "If tablet didn't receive the event, check:\n";
    echo "   1. Reverb service is running\n";
    echo "   2. Tablet is subscribed to 'orders.{$orderId}' channel\n";
    echo "   3. Laravel logs for broadcast errors\n\n";
}

echo "═════════════════════════════════════════════════════════════\n\n";
