<?php

use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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
    expect(Hash::check($plainCode, (string) $device->security_code))->toBeTrue();
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
