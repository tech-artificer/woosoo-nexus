<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LocalBranchIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_uses_the_only_branch_when_branch_is_missing(): void
    {
        $branch = Branch::create([
            'name' => 'Local Branch',
            'location' => 'On Prem Site',
        ]);

        $device = Device::create([
            'name' => 'tablet-01',
            'ip_address' => '192.168.10.10',
        ]);

        $this->assertSame($branch->id, $device->branch_id);
    }

    public function test_device_falls_back_to_single_branch_when_install_has_only_one_branch(): void
    {
        $branch = Branch::create([
            'name' => 'Single Branch',
            'location' => 'Only Site',
        ]);

        $device = Device::create([
            'name' => 'tablet-02',
            'ip_address' => '192.168.10.11',
        ]);

        $this->assertSame($branch->id, $device->branch_id);
    }

    public function test_device_creation_fails_when_local_branch_is_ambiguous(): void
    {
        Branch::create([
            'name' => 'Branch A',
            'location' => 'Site A',
        ]);
        Branch::create([
            'name' => 'Branch B',
            'location' => 'Site B',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exactly one branch record');

        Device::create([
            'name' => 'tablet-03',
            'ip_address' => '192.168.10.12',
        ]);
    }

    public function test_device_order_inherits_branch_from_device_before_single_branch_default(): void
    {
        $deviceBranch = Branch::create([
            'name' => 'Device Branch',
            'location' => 'Device Site',
        ]);
        Branch::create([
            'name' => 'Other Branch',
            'location' => 'Other Site',
        ]);

        $device = Device::create([
            'name' => 'printer-01',
            'ip_address' => '192.168.10.20',
            'branch_id' => $deviceBranch->id,
        ]);

        $order = DeviceOrder::create([
            'order_id' => 9001,
            'device_id' => $device->id,
            'guest_count' => 2,
            'total' => 12,
            'subtotal' => 12,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $this->createTestSession(),
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $this->assertSame($deviceBranch->id, $order->branch_id);
    }
}