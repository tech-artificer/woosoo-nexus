<?php

use Illuminate\Support\Facades\Redis;

test('testing environment does not require phpredis extension', function () {
    if (extension_loaded('redis')) {
        $this->markTestSkipped('phpredis extension is installed; fallback assertion is not applicable.');
    }

    expect((string) config('database.redis.client'))->not->toBe('phpredis');

    Redis::connection();
    expect(true)->toBeTrue();
});
