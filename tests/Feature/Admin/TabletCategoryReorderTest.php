<?php

use App\Models\TabletCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can reorder tablet categories via PUT reorder', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();

    $a = TabletCategory::create(['name' => 'Alpha', 'sort_order' => 2]);
    $b = TabletCategory::create(['name' => 'Beta', 'sort_order' => 0]);
    $c = TabletCategory::create(['name' => 'Gamma', 'sort_order' => 1]);

    $response = $this
        ->actingAs($admin)
        ->put(route('tablet-categories.reorder'), [
            'ids' => [$b->id, $c->id, $a->id],
        ]);

    $response->assertRedirect();

    expect($a->fresh()->sort_order)->toBe(2);
    expect($b->fresh()->sort_order)->toBe(0);
    expect($c->fresh()->sort_order)->toBe(1);
});

test('tablet-categories reorder requires valid ids array', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();

    $this
        ->actingAs($admin)
        ->put(route('tablet-categories.reorder'), ['ids' => 'not-an-array'])
        ->assertSessionHasErrors('ids');
});
