<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Plan E — woosoo:verify-integrity command.
 *
 * The command uses both env() and config() so test cases rely on .env.testing
 * having REVERB_APP_KEY=key / REVERB_APP_SECRET=secret / REVERB_APP_ID=290838
 * and manipulate config() values via Config::set to exercise each check.
 */
class VerifyIntegrityCommandTest extends TestCase
{
    private function setConsistentConfig(
        string $key = 'key',
        string $secret = 'secret',
        string $appId = '290838',
    ): void {
        Config::set('broadcasting.default', 'reverb');
        Config::set('reverb.apps.apps', [[
            'key'    => $key,
            'secret' => $secret,
            'app_id' => $appId,
        ]]);
        Config::set('broadcasting.connections.reverb', [
            'key'    => $key,
            'secret' => $secret,
            'app_id' => $appId,
            'options' => ['host' => '127.0.0.1', 'port' => 6001, 'scheme' => 'http'],
        ]);
    }

    /**
     * All checks consistent with .env.testing values → exit 0.
     * (.env.testing: REVERB_APP_KEY=key, REVERB_APP_SECRET=secret, REVERB_APP_ID=290838)
     */
    public function test_all_consistent_exits_zero(): void
    {
        $this->setConsistentConfig();

        $this->artisan('woosoo:verify-integrity')->assertExitCode(0);
    }

    /**
     * Check #3 FAIL: reverb.apps key diverges from broadcasting.connections.reverb key.
     */
    public function test_reverb_apps_vs_broadcasting_key_mismatch_exits_one(): void
    {
        $this->setConsistentConfig();

        // Diverge broadcasting connection key from reverb app key
        Config::set('broadcasting.connections.reverb.key', 'mismatched-broadcast-key');

        $this->artisan('woosoo:verify-integrity')->assertExitCode(1);
    }

    /**
     * Check #3 FAIL: app_id mismatch.
     */
    public function test_reverb_apps_vs_broadcasting_app_id_mismatch_exits_one(): void
    {
        $this->setConsistentConfig();
        Config::set('broadcasting.connections.reverb.app_id', '000000');

        $this->artisan('woosoo:verify-integrity')->assertExitCode(1);
    }

    /**
     * Check #4 FAIL: config key differs from env key (stale config cache simulation).
     * env('REVERB_APP_KEY') = 'key' from .env.testing;
     * setting config to a different value triggers FAIL.
     */
    public function test_env_vs_config_key_divergence_exits_one(): void
    {
        $this->setConsistentConfig();

        // Overwrite config key so it differs from env('REVERB_APP_KEY') = 'key'
        Config::set('reverb.apps.apps', [[
            'key'    => 'config-cached-different-key',
            'secret' => 'secret',
            'app_id' => '290838',
        ]]);
        Config::set('broadcasting.connections.reverb.key', 'config-cached-different-key');

        $this->artisan('woosoo:verify-integrity')->assertExitCode(1);
    }

    /**
     * Check #2 FAIL: broadcasting driver resolved to null-string.
     */
    public function test_broadcast_driver_null_string_exits_one(): void
    {
        $this->setConsistentConfig();
        Config::set('broadcasting.default', 'null');

        $this->artisan('woosoo:verify-integrity')->assertExitCode(1);
    }

    /**
     * Redaction: command output must never contain the raw secret value.
     * Uses a unique secret to ensure the assertion is meaningful.
     */
    public function test_command_output_never_contains_raw_secret(): void
    {
        // Use a distinctive secret that won't appear as a field name
        $rawSecret = 'xS3cR3t-UniqueTestValue-42';
        $this->setConsistentConfig(secret: $rawSecret);

        // Capture artisan output
        $output = '';
        $this->artisan('woosoo:verify-integrity')
            ->expectsOutputToContain('PASS');

        // Re-run capturing output explicitly via Artisan facade
        \Illuminate\Support\Facades\Artisan::call('woosoo:verify-integrity');
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringNotContainsString($rawSecret, $output);
    }
}
