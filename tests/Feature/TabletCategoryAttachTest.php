<?php

use App\Models\TabletCategory;
use App\Models\TabletCategoryMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can attach menus to a tablet category', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();
    $category = TabletCategory::create(['name' => 'Test Category', 'sort_order' => 0]);

    $response = $this
        ->actingAs($admin)
        ->post(route('tablet-categories.menus.attach', $category), [
            'menu_ids' => [101, 202, 303],
        ]);

    $response->assertRedirect();

    expect(TabletCategoryMenu::where('tablet_category_id', $category->id)->count())->toBe(3);
    expect(TabletCategoryMenu::where('krypton_menu_id', 101)->exists())->toBeTrue();
});

test('attach menus via json body (inertia router.post path)', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();
    $category = TabletCategory::create(['name' => 'Test Category', 'sort_order' => 0]);

    $this
        ->actingAs($admin)
        ->postJson(route('tablet-categories.menus.attach', $category), [
            'menu_ids' => [101, 202],
        ]);

    expect(TabletCategoryMenu::where('tablet_category_id', $category->id)->count())->toBe(2);
});

test('attach menus skips already-attached ids', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();
    $category = TabletCategory::create(['name' => 'Test Category', 'sort_order' => 0]);
    $category->menuPivots()->create(['krypton_menu_id' => 101, 'sort_order' => 0, 'is_featured' => false]);

    $response = $this
        ->actingAs($admin)
        ->post(route('tablet-categories.menus.attach', $category), [
            'menu_ids' => [101, 202],
        ]);

    $response->assertRedirect();
    expect(TabletCategoryMenu::where('tablet_category_id', $category->id)->count())->toBe(2);
});

test('attach menus rejects empty menu_ids', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();
    $category = TabletCategory::create(['name' => 'Test Category', 'sort_order' => 0]);

    $this
        ->actingAs($admin)
        ->post(route('tablet-categories.menus.attach', $category), [
            'menu_ids' => [],
        ])
        ->assertSessionHasErrors('menu_ids');
});

test('attach menus rejects non-integer ids', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();
    $category = TabletCategory::create(['name' => 'Test Category', 'sort_order' => 0]);

    $this
        ->actingAs($admin)
        ->post(route('tablet-categories.menus.attach', $category), [
            'menu_ids' => ['not-an-id'],
        ])
        ->assertSessionHasErrors('menu_ids.0');
});
