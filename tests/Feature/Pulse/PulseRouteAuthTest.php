<?php

use App\Models\User;

test('pulse dashboard blocks unauthenticated visitors with 403', function () {
    $this->get('/pulse')
        ->assertForbidden();
});

test('pulse dashboard blocks non-admin users with 403', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/pulse')
        ->assertForbidden();
});

test('pulse dashboard is accessible to admin users', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/pulse')
        ->assertOk();
});
