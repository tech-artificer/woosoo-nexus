<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GET users/{id} redirects to users edit', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this
        ->actingAs($admin)
        ->get(route('users.show', $target))
        ->assertRedirect(route('users.edit', $target));
});
