<?php

use App\Enums\OrderStatus;
use App\Events\PrintOrder;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function dispatchTestDevice(): Device
{
    Branch::create(['name' => 'Main', 'location' => 'HQ']);

    return Device::create([
        'name' => 'Kitchen Station 1',
        'ip_address' => '127.0.0.20',
        'is_active' => true,
        'table_id' => 1,
        'branch_id' => 1,
    ]);
}

test('dispatch does not fire PrintOrder when the order does not exist (P1-06)', function () {
    Event::fake([PrintOrder::class]);
    Sanctum::actingAs(dispatchTestDevice(), [], 'device');

    $this->getJson('/api/order/999999/dispatch')
        ->assertNotFound();

    // The pre-fix code dispatched PrintOrder with a null order — guard must prevent that.
    Event::assertNotDispatched(PrintOrder::class);
});

test('dispatch fires PrintOrder for an existing order (P1-06)', function () {
    Event::fake([PrintOrder::class]);
    Sanctum::actingAs(dispatchTestDevice(), [], 'device');

    DeviceOrder::factory()->create([
        'order_id' => 7001,
        'status' => OrderStatus::IN_PROGRESS,
    ]);

    $this->getJson('/api/order/7001/dispatch')
        ->assertOk()
        ->assertJson(['status' => 'dispatched']);

    Event::assertDispatched(PrintOrder::class);
});
