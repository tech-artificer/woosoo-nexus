<?php

use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('admin can create a device and gets auto-generated security code when none is provided', function () {
    $this->withoutVite();

    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('devices.store'), [
        'name' => 'Tablet A',
        'ip_address' => '192.168.100.51',
        'port' => 3000,
        'table_id' => null,
    ]);

    $response
        ->assertRedirect(route('devices.index'))
        ->assertSessionHas('security_code_reveal');

    $device = Device::query()->where('name', 'Tablet A')->first();

    expect($device)->not->toBeNull();
    expect((int) $device->branch_id)->toBe((int) $branch->id);
    expect((string) $device->port)->toBe('3000');
    expect($device->security_code)->not->toBeNull();
    expect($device->security_code_generated_at)->not->toBeNull();
    $plainCode = (string) session('security_code_reveal');

    expect($plainCode)->toMatch('/^\d{6}$/');
    expect(array_key_exists('security_code_plain', $device->getAttributes()))->toBeFalse();
    expect(Hash::check($plainCode, (string) $device->security_code))->toBeTrue();
});

test('devices index exposes one-time security code flash to inertia props', function () {
    $this->withoutVite();

    Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this
        ->actingAs($admin)
        ->withSession(['security_code_reveal' => '123456'])
        ->get(route('devices.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Index')
            ->where('flash.security_code_reveal', '123456')
        );
});

test('device create fails gracefully when branch context is ambiguous', function () {
    $this->withoutVite();

    Branch::create([
        'name' => 'Branch One',
        'location' => 'HQ-1',
    ]);

    Branch::create([
        'name' => 'Branch Two',
        'location' => 'HQ-2',
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('devices.store'), [
        'name' => 'Tablet B',
        'ip_address' => '192.168.100.52',
        'port' => 3000,
        'table_id' => null,
    ]);

    $response
        ->assertSessionHasErrors('branch')
        ->assertSessionHasInput('name', 'Tablet B');

    $this->assertDatabaseMissing('devices', [
        'name' => 'Tablet B',
        'ip_address' => '192.168.100.52',
    ]);
});

test('admin create request reactivates soft-deleted device that has same ip', function () {
    $this->withoutVite();

    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $admin = User::factory()->admin()->create();

    $old = Device::create([
        'name' => 'Old Tablet',
        'branch_id' => $branch->id,
        'ip_address' => '192.168.100.7',
        'port' => 3000,
        'is_active' => true,
        'security_code' => Hash::make('111111'),
        'security_code_generated_at' => now(),
    ]);

    $old->delete();

    $response = $this->actingAs($admin)->post(route('devices.store'), [
        'name' => 'New Tablet',
        'ip_address' => '192.168.100.7',
        'port' => 3001,
        'table_id' => null,
    ]);

    $response
        ->assertRedirect(route('devices.index'))
        ->assertSessionHas('success', 'Deactivated device reactivated.')
        ->assertSessionHas('security_code_reveal');

    $reactivated = Device::query()->where('ip_address', '192.168.100.7')->first();

    expect($reactivated)->not->toBeNull();
    expect((int) $reactivated->id)->toBe((int) $old->id);
    expect($reactivated->name)->toBe('New Tablet');
    expect((string) $reactivated->port)->toBe('3001');
    expect($reactivated->trashed())->toBeFalse();
    expect(Device::withTrashed()->where('ip_address', '192.168.100.7')->count())->toBe(1);
});

test('admin update fails gracefully when ip belongs to trashed device', function () {
    $this->withoutVite();

    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $admin = User::factory()->admin()->create();

    $active = Device::create([
        'name' => 'Active Tablet',
        'branch_id' => $branch->id,
        'ip_address' => '192.168.100.61',
        'port' => 3000,
        'is_active' => true,
        'security_code' => Hash::make('111111'),
        'security_code_generated_at' => now(),
    ]);

    $trashed = Device::create([
        'name' => 'Trashed Tablet',
        'branch_id' => $branch->id,
        'ip_address' => '192.168.100.62',
        'port' => 3001,
        'is_active' => true,
        'security_code' => Hash::make('222222'),
        'security_code_generated_at' => now(),
    ]);

    $trashed->delete();

    $response = $this->actingAs($admin)->put(route('devices.update', $active->id), [
        'name' => 'Active Tablet Updated',
        'ip_address' => '192.168.100.62',
        'port' => 3002,
        'table_id' => null,
    ]);

    $response
        ->assertSessionHasErrors('ip_address')
        ->assertSessionHasInput('ip_address', '192.168.100.62');

    expect($active->fresh()->ip_address)->toBe('192.168.100.61');
});

test('admin update persists device type', function () {
    $this->withoutVite();

    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $admin = User::factory()->admin()->create();

    $device = Device::create([
        'name' => 'Kitchen Printer',
        'branch_id' => $branch->id,
        'ip_address' => '192.168.100.63',
        'port' => 3000,
        'is_active' => true,
        'type' => 'tablet',
        'security_code' => Hash::make('444444'),
        'security_code_generated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->put(route('devices.update', $device->id), [
        'name' => 'Kitchen Printer',
        'ip_address' => '192.168.100.63',
        'port' => 3000,
        'table_id' => null,
        'type' => 'printer_relay',
    ]);

    $response->assertRedirect(route('devices.index'));

    expect($device->fresh()->type)->toBe('printer_relay');
});

test('devices index includes deleted_at for trashed devices so admin can see restore state', function () {
    $this->withoutVite();

    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $admin = User::factory()->admin()->create();

    $device = Device::create([
        'name' => 'Hidden Tablet',
        'branch_id' => $branch->id,
        'ip_address' => '192.168.100.71',
        'port' => 3010,
        'is_active' => true,
        'security_code' => Hash::make('333333'),
        'security_code_generated_at' => now(),
    ]);

    $device->delete();

    $this->actingAs($admin)
        ->get(route('devices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Index')
            ->has('devices', 1)
            ->where('devices.0.name', 'Hidden Tablet')
            ->where('devices.0.deleted_at', fn ($value) => filled($value))
        );
});

test('admin regenerate security code revokes tokens without storing plain code', function () {
    $this->withoutVite();

    $branch = Branch::create([
        'name' => 'Main Branch',
        'location' => 'HQ',
    ]);

    $admin = User::factory()->admin()->create();

    $device = Device::create([
        'name' => 'Regenerate Target',
        'branch_id' => $branch->id,
        'ip_address' => '192.168.100.72',
        'port' => 3011,
        'is_active' => true,
        'security_code' => Hash::make('654321'),
        'security_code_generated_at' => now(),
    ]);
    $device->createToken('device-auth', expiresAt: now()->addDays(30));

    expect($device->tokens()->count())->toBe(1);

    $response = $this
        ->actingAs($admin)
        ->post(route('devices.security-code.regenerate', $device->id));

    $response
        ->assertOk()
        ->assertJsonStructure(['security_code']);

    $plainCode = (string) $response->json('security_code');

    $device->refresh();

    expect($plainCode)->toMatch('/^\d{6}$/');
    expect(array_key_exists('security_code_plain', $device->getAttributes()))->toBeFalse();
    expect($device->tokens()->count())->toBe(0);
});
