<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DeviceOrder;
use App\Events\PrintOrder;

class DispatchPrintOrder extends Command
{
    protected $signature = 'print:dispatch {order_uuid? : Device order UUID to print}';
    protected $description = 'Dispatch PrintOrder event for an order UUID';

    public function handle()
    {
        $orderUuid = $this->argument('order_uuid');

        if (!$orderUuid) {
            // List recent unprintedorders
            $orders = DeviceOrder::where('is_printed', false)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['id', 'order_uuid', 'order_number', 'status', 'created_at', 'total']);
            
            $this->info("Recent unprinted orders:");
            foreach ($orders as $order) {
                $status = $order->status instanceof \App\Enums\OrderStatus ? $order->status->value : (string)$order->status;
                $this->line("  {$order->order_uuid} ({$order->order_number}) - {$status} - ₱{$order->total} - {$order->created_at}");
            }
            $this->info("\nUsage: php artisan print:dispatch <order_uuid>");
            return 1;
        }

        $order = DeviceOrder::query()->where('order_uuid', $orderUuid)->first();

        if (!$order) {
            $this->error("Order UUID {$orderUuid} not found");
            return 1;
        }

        PrintOrder::dispatch($order);
        
        $status = $order->status instanceof \App\Enums\OrderStatus ? $order->status->value : (string)$order->status;
        $this->info("✅ PrintOrder event dispatched for order {$order->order_uuid} ({$order->order_number}) (ID: {$order->id})");
        $this->info("   Status: {$status}");
        $this->info("   Is Printed: " . ($order->is_printed ? 'Yes' : 'No'));
        $this->info("   Table: " . ($order->table?->name ?? 'N/A'));
        $this->info("   Total: ₱{$order->total}");

        return 0;
    }
}
