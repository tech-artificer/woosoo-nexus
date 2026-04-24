<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admins can open the service requests page', function () {
    $this->withoutVite();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/service-requests')
        ->assertOk();
});