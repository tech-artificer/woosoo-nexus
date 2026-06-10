<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GET devices/trashed redirects to devices index', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();

    $this
        ->actingAs($admin)
        ->get(route('devices.trashed'))
        ->assertRedirect(route('devices.index', ['view' => 'trashed']));
});
