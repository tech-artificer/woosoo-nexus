<?php

use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('admin can load edit form', function () {
    $this->withoutVite();

    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $device = Device::create([
        'name' => 'Tablet A',
        'branch_id' => $branch->id,
        'ip_address' => '192.168.100.51',
        'port' => 3000,
        'is_active' => true,
        'security_code' => Hash::make('111111'),
        'security_code_generated_at' => now(),
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('devices.edit', $device->id));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Edit')
            ->where('device.id', $device->id)
            ->where('device.name', 'Tablet A')
            ->where('device.ip_address', '192.168.100.51')
            ->where('device.port', '3000')
        );
});

test('admin can update', function () {
    $this->withoutVite();

    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $device = Device::create([
        'name' => 'Tablet A',
        'branch_id' => $branch->id,
        'ip_address' => '192.168.100.51',
        'port' => 3000,
        'is_active' => true,
        'security_code' => Hash::make('111111'),
        'security_code_generated_at' => now(),
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->put(route('devices.update', $device->id), [
        'name' => 'Tablet A Updated',
        'ip_address' => '192.168.100.99',
        'port' => 3001,
        'table_id' => null,
        'type' => null,
        'last_ip_address' => '192.168.100.51',
    ]);

    $response->assertRedirect(route('devices.index'));

    $device->refresh();
    expect($device->name)->toBe('Tablet A Updated');
    expect($device->ip_address)->toBe('192.168.100.99');
    expect((int) $device->port)->toBe(3001);
});

test('update rejects empty name', function () {
    $this->withoutVite();

    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $device = Device::create([
        'name' => 'Tablet A',
        'branch_id' => $branch->id,
        'ip_address' => '192.168.100.51',
        'port' => 3000,
        'is_active' => true,
        'security_code' => Hash::make('111111'),
        'security_code_generated_at' => now(),
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->put(route('devices.update', $device->id), [
        'name' => '',
        'ip_address' => '192.168.100.99',
        'port' => 3001,
        'table_id' => null,
        'type' => null,
        'last_ip_address' => null,
    ]);

    $response->assertSessionHasErrors('name');
    expect($device->fresh()->name)->toBe('Tablet A');
});

test('non-admin gets 403', function () {
    $this->withoutVite();

    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $device = Device::create([
        'name' => 'Tablet A',
        'branch_id' => $branch->id,
        'ip_address' => '192.168.100.51',
        'port' => 3000,
        'is_active' => true,
        'security_code' => Hash::make('111111'),
        'security_code_generated_at' => now(),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('devices.update', $device->id), [
        'name' => 'Tablet A Updated',
        'ip_address' => '192.168.100.99',
        'port' => 3001,
        'table_id' => null,
        'type' => null,
        'last_ip_address' => null,
    ]);

    $response->assertForbidden();
    expect($device->fresh()->name)->toBe('Tablet A');
});
