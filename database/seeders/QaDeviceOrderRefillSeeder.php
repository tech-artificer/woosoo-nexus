<?php

namespace Database\Seeders;

use App\Actions\Order\CreateOrderedMenu;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\Krypton\Menu as KryptonMenu;
use App\Models\Krypton\OrderCheck;
use App\Models\Krypton\Table as KryptonTable;
use App\Services\Krypton\KryptonContextService;
use App\Services\Krypton\OrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QaDeviceOrderRefillSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🧪 Seeding QA data: 4 devices + orders + refills...');

        $branch = Branch::firstOrCreate([
            'name' => 'SM Butuan',
            'location' => 'Butuan City, Agusan del Norte, Philippines',
        ]);

        $tables = KryptonTable::query()
            ->orderBy('id')
            ->limit(4)
            ->get(['id', 'name']);

        if ($tables->count() < 4) {
            throw new \RuntimeException('Not enough POS tables found. Need at least 4 rows in pos.tables.');
        }

        $menus = KryptonMenu::query()
            ->where('is_available', true)
            ->where('is_modifier_only', false)
            ->orderBy('id')
            ->limit(8)
            ->get(['id', 'name', 'price']);

        if ($menus->count() < 4) {
            throw new \RuntimeException('Not enough POS menus found. Need at least 4 non-modifier available menus.');
        }

        $context = app(KryptonContextService::class)->getData();
        $orderService = app(OrderService::class);

        $devices = collect();

        foreach ($tables as $index => $table) {
            $name = 'QA Tablet ' . ($index + 1);
            $ipAddress = '192.168.100.' . (201 + $index);

            $device = Device::updateOrCreate(
                ['ip_address' => $ipAddress],
                [
                    'branch_id' => $branch->id,
                    'name' => $name,
                    'table_id' => (string) $table->id,
                    'is_active' => true,
                    'app_version' => 'qa-seed-v1',
                    'last_ip_address' => $ipAddress,
                    'last_seen_at' => now(),
                ]
            );

            $devices->push($device);
            $this->command->info("✅ Device: {$device->name} ({$device->ip_address}) -> POS table {$table->id}");
        }

        $createdOrders = collect();

        foreach ($devices as $deviceIndex => $device) {
            $primaryMenu = $menus[$deviceIndex % $menus->count()];
            $secondaryMenu = $menus[($deviceIndex + 1) % $menus->count()];

            $baseItems = [
                [
                    'menu_id' => (int) $primaryMenu->id,
                    'name' => (string) $primaryMenu->name,
                    'quantity' => 1,
                    'price' => (float) ($primaryMenu->price ?? 0),
                    'note' => 'QA initial item A',
                    'subtotal' => (float) ($primaryMenu->price ?? 0),
                    'tax' => round(((float) ($primaryMenu->price ?? 0)) * 0.10, 2),
                    'discount' => 0,
                    'seat_number' => 1,
                    'index' => 1,
                ],
                [
                    'menu_id' => (int) $secondaryMenu->id,
                    'name' => (string) $secondaryMenu->name,
                    'quantity' => 1,
                    'price' => (float) ($secondaryMenu->price ?? 0),
                    'note' => 'QA initial item B',
                    'subtotal' => (float) ($secondaryMenu->price ?? 0),
                    'tax' => round(((float) ($secondaryMenu->price ?? 0)) * 0.10, 2),
                    'discount' => 0,
                    'seat_number' => 1,
                    'index' => 2,
                ],
            ];

            $subtotal = collect($baseItems)->sum(fn ($item) => (float) $item['price'] * (int) $item['quantity']);
            $tax = round($subtotal * 0.10, 2);
            $total = round($subtotal + $tax, 2);

            $payload = [
                'guest_count' => 2,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => 0,
                'total_amount' => $total,
                'items' => $baseItems,
            ];

            $deviceOrder = $orderService->processOrder($device, $payload);

            if (! $deviceOrder instanceof DeviceOrder) {
                throw new \RuntimeException("Order creation failed for device {$device->name}");
            }

            $createdOrders->push($deviceOrder);
            $this->command->info("✅ Order: device_order_id={$deviceOrder->id}, order_id={$deviceOrder->order_id}");
        }

        foreach ($createdOrders as $orderIndex => $deviceOrder) {
            $refillMenu = $menus[($orderIndex + 2) % $menus->count()];
            $refillMenu2 = $menus[($orderIndex + 3) % $menus->count()];

            $orderCheckId = OrderCheck::query()
                ->where('order_id', $deviceOrder->order_id)
                ->orderByDesc('id')
                ->value('id');

            $refillItems = [
                [
                    'menu_id' => (int) $refillMenu->id,
                    'quantity' => 1,
                    'seat_number' => 1,
                    'index' => 101,
                    'note' => 'QA refill item A',
                    'price' => (float) ($refillMenu->price ?? 0),
                ],
                [
                    'menu_id' => (int) $refillMenu2->id,
                    'quantity' => 1,
                    'seat_number' => 1,
                    'index' => 102,
                    'note' => 'QA refill item B',
                    'price' => (float) ($refillMenu2->price ?? 0),
                ],
            ];

            CreateOrderedMenu::run([
                'order_id' => $deviceOrder->order_id,
                'order_check_id' => $orderCheckId,
                'employee_log_id' => $context['employee_log_id'] ?? 1,
                'device_order_id' => $deviceOrder->id,
                'items' => $refillItems,
            ]);

            $this->command->info("✅ Refill: order_id={$deviceOrder->order_id}, order_check_id=" . ($orderCheckId ?? 'null'));
        }

        $deviceCount = Device::where('name', 'like', 'QA Tablet %')->count();
        $orderCount = DeviceOrder::whereIn('device_id', $devices->pluck('id'))->count();
        $refillLocalCount = DB::table('device_order_items')
            ->whereIn('order_id', $createdOrders->pluck('id'))
            ->whereIn('index', [101, 102])
            ->count();

        $this->command->info('');
        $this->command->info("📊 QA Seed Summary: devices={$deviceCount}, orders={$orderCount}, refill_items={$refillLocalCount}");
        $this->command->info('ℹ️  QA device IPs: 192.168.100.201-204');
    }
}
