<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected away from the KDS display', function () {
    $this->get('/kds')->assertRedirect('/login');
});

test('non-admin users cannot access the KDS display', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/kds')
        ->assertForbidden();
});

test('admins can open the KDS display with a tickets prop', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/kds')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('KDS/Display')
            ->where('title', 'Kitchen Display')
            ->has('initialTickets')
        );
});

test('kds index response includes serverNow as an integer millisecond epoch', function () {
    $admin = User::factory()->admin()->create();

    $before = (int) (microtime(true) * 1000);

    $this->actingAs($admin)
        ->get('/kds')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('KDS/Display')
            ->has('serverNow')
            ->where('serverNow', fn ($value) => is_int($value) && $value >= $before)
        );
});
