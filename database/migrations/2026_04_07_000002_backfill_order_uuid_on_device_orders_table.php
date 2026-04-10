<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('device_orders')
            ->whereNull('order_uuid')
            ->orderBy('id')
            ->select(['id', 'created_at', 'order_number'])
            ->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    $orderUuid = (string) Str::uuid();
                    $createdAt = $order->created_at ? \Carbon\Carbon::parse($order->created_at) : now();
                    $displayOrderNumber = $order->order_number;

                    if (empty($displayOrderNumber)) {
                        $displayOrderNumber = 'ORD-'
                            . $createdAt->format('Ymd')
                            . '-'
                            . strtoupper(substr($orderUuid, -6));
                    }

                    DB::table('device_orders')
                        ->where('id', $order->id)
                        ->update([
                            'order_uuid' => $orderUuid,
                            'order_number' => $displayOrderNumber,
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        DB::table('device_orders')->update(['order_uuid' => null]);
    }
};
