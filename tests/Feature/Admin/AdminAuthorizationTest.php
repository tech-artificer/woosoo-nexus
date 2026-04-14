<?php

use App\Models\Branch;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('non-admin users cannot access users index', function () {
    $this->withoutVite();

    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/users')
        ->assertForbidden();
});

test('admins can access users index', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/users')
        ->assertOk();
});

test('non-admin users cannot access branches index', function () {
    $this->withoutVite();

    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/branches')
        ->assertForbidden();
});

test('admins with branch permission can access branches index', function () {
    $this->withoutVite();

    Permission::findOrCreate('view branches', 'web');
    Branch::create(['name' => 'Main', 'location' => 'HQ']);

    $admin = User::factory()->admin()->create();
    $admin->givePermissionTo('view branches');

    $this->actingAs($admin)
        ->get('/branches')
        ->assertOk();
});

test('admins without create branch permission cannot create branches', function () {
    $this->withoutVite();

    Permission::findOrCreate('create branches', 'web');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/branches', [
            'name' => 'North',
            'location' => 'Annex',
        ])
        ->assertForbidden();
});

test('admins with create branch permission can create branches', function () {
    $this->withoutVite();

    Permission::findOrCreate('create branches', 'web');
    $admin = User::factory()->admin()->create();
    $admin->givePermissionTo('create branches');

    $this->actingAs($admin)
        ->from('/branches')
        ->post('/branches', [
            'name' => 'North',
            'location' => 'Annex',
        ])
        ->assertRedirect('/branches');

    $this->assertDatabaseHas('branches', [
        'name' => 'North',
        'location' => 'Annex',
    ]);
});

test('admins cannot create a second branch in a single-branch install', function () {
    $this->withoutVite();

    Permission::findOrCreate('create branches', 'web');
    Branch::create(['name' => 'Main', 'location' => 'HQ']);

    $admin = User::factory()->admin()->create();
    $admin->givePermissionTo('create branches');

    $this->actingAs($admin)
        ->from('/branches')
        ->post('/branches', [
            'name' => 'North',
            'location' => 'Annex',
        ])
        ->assertRedirect('/branches')
        ->assertSessionHas('error');

    expect(Branch::withTrashed()->count())->toBe(1);
});

test('admins with update branch permission can update branches', function () {
    $this->withoutVite();

    Permission::findOrCreate('update branches', 'web');
    $branch = Branch::create(['name' => 'Main', 'location' => 'HQ']);
    $admin = User::factory()->admin()->create();
    $admin->givePermissionTo('update branches');

    $this->actingAs($admin)
        ->from('/branches')
        ->put('/branches/' . $branch->id, [
            'name' => 'Main Updated',
            'location' => 'HQ 2',
        ])
        ->assertRedirect('/branches');

    $this->assertDatabaseHas('branches', [
        'id' => $branch->id,
        'name' => 'Main Updated',
        'location' => 'HQ 2',
    ]);
});

test('admins with delete branch permission can delete branches', function () {
    $this->withoutVite();

    Permission::findOrCreate('delete branches', 'web');
    Branch::create(['name' => 'Main', 'location' => 'HQ']);
    $branch = Branch::create(['name' => 'Overflow', 'location' => 'Legacy']);
    $admin = User::factory()->admin()->create();
    $admin->givePermissionTo('delete branches');

    $this->actingAs($admin)
        ->from('/branches')
        ->delete('/branches/' . $branch->id)
        ->assertRedirect('/branches');

    $this->assertSoftDeleted('branches', [
        'id' => $branch->id,
    ]);
});

test('admins cannot delete the only branch in a single-branch install', function () {
    $this->withoutVite();

    Permission::findOrCreate('delete branches', 'web');
    $branch = Branch::create(['name' => 'Main', 'location' => 'HQ']);
    $admin = User::factory()->admin()->create();
    $admin->givePermissionTo('delete branches');

    $this->actingAs($admin)
        ->from('/branches')
        ->delete('/branches/' . $branch->id)
        ->assertRedirect('/branches')
        ->assertSessionHas('error');

    $this->assertDatabaseHas('branches', [
        'id' => $branch->id,
        'deleted_at' => null,
    ]);
});