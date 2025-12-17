<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    // Create a user with admin privileges so the `can:admin` gate passes.
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response->assertStatus(200);
});