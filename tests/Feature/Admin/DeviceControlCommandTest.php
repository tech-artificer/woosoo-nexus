<?php

use App\Events\AppControlEvent;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// Shared setup — mirrors DeviceEditTest.php's Branch/Device/admin/non-admin pattern.
beforeEach(function () {
    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $this->device = Device::create([
        'name' => 'Tablet A',
        'branch_id' => $branch->id,
        'ip_address' => '192.168.100.51',
        'port' => 3000,
        'is_active' => true,
        'security_code' => Hash::make('111111'),
        'security_code_generated_at' => now(),
    ]);

    $this->admin = User::factory()->admin()->create();
    $this->nonAdmin = User::factory()->create();
});

test('admin can send a message control command', function () {
    Event::fake([AppControlEvent::class]);

    $this->actingAs($this->admin)
        ->postJson(route('devices.control', $this->device->id), [
            'action' => 'message',
            'message' => 'Hi',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    Event::assertDispatched(AppControlEvent::class, function (AppControlEvent $event) {
        return $event->deviceId === $this->device->id
            && $event->action === 'message'
            && $event->payload === ['message' => 'Hi'];
    });
});

test('admin can send a restart control command', function () {
    Event::fake([AppControlEvent::class]);

    $this->actingAs($this->admin)
        ->postJson(route('devices.control', $this->device->id), [
            'action' => 'restart',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    Event::assertDispatched(AppControlEvent::class, function (AppControlEvent $event) {
        return $event->deviceId === $this->device->id
            && $event->action === 'restart'
            && $event->payload === [];
    });
});

test('non-admin gets 403 and no event is dispatched', function () {
    Event::fake([AppControlEvent::class]);

    $this->actingAs($this->nonAdmin)
        ->postJson(route('devices.control', $this->device->id), [
            'action' => 'restart',
        ])
        ->assertForbidden();

    Event::assertNotDispatched(AppControlEvent::class);
});

test('invalid action is rejected with a validation error', function () {
    Event::fake([AppControlEvent::class]);

    $this->actingAs($this->admin)
        ->postJson(route('devices.control', $this->device->id), [
            'action' => 'lock',
        ])
        ->assertStatus(422);

    Event::assertNotDispatched(AppControlEvent::class);
});
