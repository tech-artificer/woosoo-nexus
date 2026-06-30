<?php

use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// Shared setup — avoids repeating Branch+Device+User creation in every test.
beforeEach(function () {
    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $this->device = Device::create([
        'name'                       => 'Tablet A',
        'branch_id'                  => $branch->id,
        'ip_address'                 => '192.168.100.51',
        'port'                       => 3000,
        'is_active'                  => true,
        'security_code'              => Hash::make('111111'),
        'security_code_generated_at' => now(),
    ]);

    $this->admin    = User::factory()->admin()->create();
    $this->nonAdmin = User::factory()->create();
});

test('admin can load edit form', function () {
    $this->withoutVite();

    $this->actingAs($this->admin)
        ->get(route('devices.edit', $this->device->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Edit')
            ->where('device.id', $this->device->id)
            ->where('device.name', 'Tablet A')
            ->where('device.ip_address', '192.168.100.51')
            ->where('device.port', '3000')
        );
});

test('guest is redirected to login on edit GET', function () {
    $this->withoutVite();

    $this->get(route('devices.edit', $this->device->id))
        ->assertRedirect(route('login'));
});

test('non-admin gets 403 on edit GET', function () {
    $this->withoutVite();

    $this->actingAs($this->nonAdmin)
        ->get(route('devices.edit', $this->device->id))
        ->assertForbidden();
});

test('admin can update name, ip, and last_ip_address', function () {
    $this->withoutVite();

    $this->actingAs($this->admin)
        ->put(route('devices.update', $this->device->id), [
            'name'            => 'Tablet A Updated',
            'ip_address'      => '192.168.100.99',
            'port'            => 3001,
            'table_id'        => null,
            'type'            => null,
            'last_ip_address' => '192.168.100.51',
        ])
        ->assertRedirect(route('devices.index'));

    $this->device->refresh();
    expect($this->device->name)->toBe('Tablet A Updated');
    expect($this->device->ip_address)->toBe('192.168.100.99');
    expect((int) $this->device->port)->toBe(3001);
    expect($this->device->last_ip_address)->toBe('192.168.100.51');
});

test('update rejects empty name', function () {
    $this->actingAs($this->admin)
        ->put(route('devices.update', $this->device->id), [
            'name'            => '',
            'ip_address'      => '192.168.100.99',
            'port'            => null,
            'table_id'        => null,
            'type'            => null,
            'last_ip_address' => null,
        ])
        ->assertSessionHasErrors('name');

    expect($this->device->fresh()->name)->toBe('Tablet A');
});

test('update rejects invalid ip in last_ip_address', function () {
    $this->actingAs($this->admin)
        ->put(route('devices.update', $this->device->id), [
            'name'            => 'Tablet A',
            'ip_address'      => '192.168.100.51',
            'port'            => null,
            'table_id'        => null,
            'type'            => null,
            'last_ip_address' => 'not-an-ip',
        ])
        ->assertSessionHasErrors('last_ip_address');
});

test('non-admin gets 403 on update PUT', function () {
    $this->actingAs($this->nonAdmin)
        ->put(route('devices.update', $this->device->id), [
            'name'            => 'Tablet A Updated',
            'ip_address'      => '192.168.100.99',
            'port'            => null,
            'table_id'        => null,
            'type'            => null,
            'last_ip_address' => null,
        ])
        ->assertForbidden();

    expect($this->device->fresh()->name)->toBe('Tablet A');
});
