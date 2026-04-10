<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertJsonStructure([
            'success',
            'data' => [
                'status',
                'services' => ['mysql', 'pos', 'redis'],
                'queue_depth',
                'version',
                'environment',
                'uptime_seconds',
            ],
        ]);

        $this->assertContains(
            $response->json('data.status'),
            ['ok', 'degraded', 'down']
        );
    }

    public function test_health_mysql_is_true_when_connected(): void
    {
        $response = $this->getJson('/api/health');
        $this->assertTrue($response->json('data.services.mysql'));
    }

    public function test_health_http_status_matches_overall_status(): void
    {
        $response = $this->getJson('/api/health');
        $status = $response->json('data.status');

        $expected = match ($status) {
            'ok'       => 200,
            'degraded' => 207,
            'down'     => 503,
            default    => 200,
        };

        $response->assertStatus($expected);
    }

    public function test_health_uptime_is_positive_integer(): void
    {
        $response = $this->getJson('/api/health');
        $uptime = $response->json('data.uptime_seconds');

        $this->assertIsInt($uptime);
        $this->assertGreaterThanOrEqual(0, $uptime);
    }
}
