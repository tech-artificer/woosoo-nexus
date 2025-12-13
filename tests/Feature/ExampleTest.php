<?php

test('root redirects to login when guest', function () {
    $response = $this->get('/');
    $response->assertStatus(302);
});