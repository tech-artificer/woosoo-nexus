<?php

test('registration routes are disabled for public', function () {
    $response = $this->get('/register');
    expect($response->status())->toBeIn([404, 405]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    expect($response->status())->toBeIn([404, 405]);
});