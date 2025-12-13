<?php

test('registration routes are disabled for public', function () {
    $response = $this->get('/register');
    $response->assertStatus(404);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    $response->assertStatus(404);
});