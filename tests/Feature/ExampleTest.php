<?php

test('root returns certificate page for guest', function () {
    $response = $this->get('/');
    $response->assertOk();
});