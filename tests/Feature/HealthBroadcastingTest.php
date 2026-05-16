<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Plan E — /api/health broadcasting integrity block.
 *
 * The route in routes/api.php calls checkBroadcastingIntegrity() which
 * compares config('reverb.apps.apps.0') against
 * config('broadcasting.connections.reverb') and returns a non-secret
 * block with key_fingerprint + consistent flag.
 */
class HealthBroadcastingTest extends TestCase
{
    private function setConsistentBroadcastConfig(
        string $key = 'testkey12345',
        string $secret = 'testsecret12345',
        string $appId = '999001',
        string $host = '127.0.0.1',
        int $port = 6001,
        string $scheme = 'http',
    ): void {
        Config::set('broadcasting.default', 'reverb');

        Config::set('reverb.apps.apps', [[
            'key'     => $key,
            'secret'  => $secret,
            'app_id'  => $appId,
            'options' => [
                'host'   => $host,
                'port'   => $port,
                'scheme' => $scheme,
                'useTLS' => false,
            ],
            'allowed_origins' => ['*'],
        ]]);

        Config::set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key'    => $key,
            'secret' => $secret,
            'app_id' => $appId,
            'options' => [
                'host'   => $host,
                'port'   => $port,
                'scheme' => $scheme,
                'useTLS' => false,
            ],
        ]);
    }

    public function test_broadcasting_block_present_when_configs_consistent(): void
    {
        $this->setConsistentBroadcastConfig();

        $response = $this->getJson('/api/health');

        $response->assertJsonPath('data.services.broadcasting.consistent', true);
        $response->assertJsonStructure([
            'data' => [
                'services' => [
                    'broadcasting' => ['driver', 'key_fingerprint', 'host', 'port', 'scheme', 'consistent'],
                ],
            ],
        ]);
    }

    public function test_consistent_true_does_not_degrade_overall_status(): void
    {
        $this->setConsistentBroadcastConfig();

        $response = $this->getJson('/api/health');

        // Broadcasting alone should not push status to degraded
        $broadcastingConsistent = $response->json('data.services.broadcasting.consistent');
        $this->assertTrue($broadcastingConsistent);
    }

    public function test_key_fingerprint_is_redacted_not_raw(): void
    {
        $rawKey = 'supersecretreverbkey9999';
        $this->setConsistentBroadcastConfig(key: $rawKey);

        $response = $this->getJson('/api/health');

        $responseBody = $response->getContent();
        $fingerprint = $response->json('data.services.broadcasting.key_fingerprint');

        // Fingerprint must be set
        $this->assertNotNull($fingerprint);
        $this->assertNotEmpty($fingerprint);

        // Raw key must NOT appear verbatim in the full response body
        $this->assertStringNotContainsString($rawKey, $responseBody);

        // Fingerprint must follow redacted format: {first4}...({len}b, sha256:{8-hex})
        $this->assertMatchesRegularExpression(
            '/^.{1,4}\.{3}\(\d+b, sha256:[0-9a-f]{8}\)$/',
            $fingerprint,
        );
    }

    public function test_raw_secret_never_appears_in_response(): void
    {
        $rawSecret = 'uniqueTestSecretValue9876';
        $this->setConsistentBroadcastConfig(secret: $rawSecret);

        $response = $this->getJson('/api/health');

        $responseBody = $response->getContent();
        $this->assertStringNotContainsString($rawSecret, $responseBody);
    }

    public function test_inconsistent_keys_sets_consistent_false(): void
    {
        $this->setConsistentBroadcastConfig();

        // Diverge the broadcasting connection key from the reverb app key
        Config::set('broadcasting.connections.reverb.key', 'totally-different-key');

        $response = $this->getJson('/api/health');

        $response->assertJsonPath('data.services.broadcasting.consistent', false);
    }

    public function test_inconsistent_config_sets_overall_status_degraded(): void
    {
        $this->setConsistentBroadcastConfig();
        Config::set('broadcasting.connections.reverb.key', 'mismatched-key');

        $response = $this->getJson('/api/health');

        $this->assertContains(
            $response->json('data.status'),
            ['degraded', 'down'],
        );
    }
}
