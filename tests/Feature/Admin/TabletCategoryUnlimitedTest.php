<?php

use App\Models\TabletCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create a tablet category flagged unlimited', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();

    $this
        ->actingAs($admin)
        ->post(route('tablet-categories.store'), [
            'name' => 'Sides',
            'is_active' => true,
            'is_unlimited' => true,
        ])
        ->assertRedirect();

    expect(TabletCategory::where('slug', 'sides')->first()->is_unlimited)->toBeTrue();
});

test('admin can toggle is_unlimited via update', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();
    $category = TabletCategory::create(['name' => 'Drinks', 'is_unlimited' => false]);

    $this
        ->actingAs($admin)
        ->put(route('tablet-categories.update', $category), [
            'name' => 'Drinks',
            'is_unlimited' => true,
        ])
        ->assertRedirect();

    expect($category->fresh()->is_unlimited)->toBeTrue();
});

test('update without is_unlimited leaves the flag untouched', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();
    $category = TabletCategory::create(['name' => 'Sides', 'is_unlimited' => true]);

    // toggleActive() in the admin UI PUTs without is_unlimited — must not reset it.
    $this
        ->actingAs($admin)
        ->put(route('tablet-categories.update', $category), [
            'name' => 'Sides',
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($category->fresh()->is_unlimited)->toBeTrue();
});
