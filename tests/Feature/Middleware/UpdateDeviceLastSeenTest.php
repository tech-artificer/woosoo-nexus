<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UpdateDeviceLastSeenTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_tablet_api_request_updates_device_last_seen_at(): void
    {
        Cache::flush();

        $device = Device::factory()->create([
            'is_active' => true,
            'last_seen_at' => Carbon::parse('2026-05-20 10:00:00'),
        ]);
        $token = $device->createToken('tablet')->plainTextToken;
        $seenAt = Carbon::parse('2026-05-21 12:00:00');

        Carbon::setTestNow($seenAt);

        $this->withToken($token, 'Bearer')
            ->getJson('/api/v2/tablet/categories')
            ->assertOk();

        $this->assertSame(
            $seenAt->toDateTimeString(),
            $device->refresh()->last_seen_at?->toDateTimeString()
        );
    }

    public function test_last_seen_write_is_throttled_per_device(): void
    {
        config(['devices.last_seen_write_throttle_seconds' => 30]);
        Cache::flush();

        $device = Device::factory()->create([
            'is_active' => true,
            'last_seen_at' => Carbon::parse('2026-05-20 10:00:00'),
        ]);
        $token = $device->createToken('tablet')->plainTextToken;
        $firstSeenAt = Carbon::parse('2026-05-21 12:00:00');
        $secondSeenAt = Carbon::parse('2026-05-21 12:00:10');

        Carbon::setTestNow($firstSeenAt);
        $this->withToken($token, 'Bearer')
            ->getJson('/api/v2/tablet/categories')
            ->assertOk();

        Carbon::setTestNow($secondSeenAt);
        $this->withToken($token, 'Bearer')
            ->getJson('/api/v2/tablet/categories')
            ->assertOk();

        $this->assertSame(
            $firstSeenAt->toDateTimeString(),
            $device->refresh()->last_seen_at?->toDateTimeString()
        );
    }
}
