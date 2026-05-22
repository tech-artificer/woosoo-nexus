<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class VerifyIntegrityCommand extends Command
{
    protected $signature = 'woosoo:verify-integrity';

    protected $description = 'Verify Reverb configuration integrity to catch silent broadcast key/id mismatches.';

    public function handle(): int
    {
        $checks = [];
        $hasFail = false;

        // Check 1: Required env vars non-empty
        $check1 = $this->checkRequiredEnvVars($checks);
        if ($check1 === 'FAIL') {
            $hasFail = true;
        }

        // Check 2: Broadcasting driver resolution
        $check2 = $this->checkBroadcastingDriverResolution($checks);
        if ($check2 === 'FAIL') {
            $hasFail = true;
        }

        // Check 3: Config consistency (reverb.apps vs broadcasting.connections.reverb)
        $check3 = $this->checkConfigConsistency($checks);
        if ($check3 === 'FAIL') {
            $hasFail = true;
        }

        // Check 4: Env vs config divergence (stale cache detection)
        $check4 = $this->checkEnvVsConfigDivergence($checks);
        if ($check4 === 'FAIL') {
            $hasFail = true;
        }

        // Check 5: Reverb server vs client-facing values
        $check5 = $this->checkServerVsClientFacing($checks);
        if ($check5 === 'FAIL') {
            $hasFail = true;
        }

        // Check 6: APP_KEY and POS config (warning only)
        $this->checkAppKeyAndPosConfig($checks);

        // Print table
        $this->printTable($checks);

        return $hasFail ? 1 : 0;
    }

    private function checkRequiredEnvVars(&$checks): string
    {
        $appId = trim((string) env('REVERB_APP_ID'));
        $appKey = trim((string) env('REVERB_APP_KEY'));
        $appSecret = trim((string) env('REVERB_APP_SECRET'));

        if ($appId === '' || $appKey === '' || $appSecret === '') {
            $checks[] = [
                'Check',
                'Required env vars (REVERB_APP_ID/KEY/SECRET)',
                'FAIL',
                'One or more required env vars are empty',
            ];
            return 'FAIL';
        }

        $checks[] = [
            'Check',
            'Required env vars (REVERB_APP_ID/KEY/SECRET)',
            'PASS',
            'All required env vars present',
        ];
        return 'PASS';
    }

    private function checkBroadcastingDriverResolution(&$checks): string
    {
        $broadcastDriver = config('broadcasting.default');

        if ($broadcastDriver === 'null') {
            $checks[] = [
                'Check',
                'Broadcasting driver resolution',
                'FAIL',
                'Driver resolved to "null" — check BROADCAST_CONNECTION, REVERB_APP_KEY, PUSHER_APP_KEY',
            ];
            return 'FAIL';
        }

        if ($broadcastDriver !== 'reverb') {
            // If explicitly set to something other than reverb, that's a warning-level issue
            $checks[] = [
                'Check',
                'Broadcasting driver resolution',
                'WARN',
                "Driver is '{$broadcastDriver}' (not 'reverb')",
            ];
            return 'WARN';
        }

        // Check if BROADCAST_CONNECTION is unset but reverb is inferred
        if (env('BROADCAST_CONNECTION') === null && env('REVERB_APP_KEY')) {
            $checks[] = [
                'Check',
                'Broadcasting driver resolution',
                'WARN',
                'BROADCAST_CONNECTION unset; reverb inferred from REVERB_APP_KEY (explicit setting recommended)',
            ];
            return 'WARN';
        }

        $checks[] = [
            'Check',
            'Broadcasting driver resolution',
            'PASS',
            'Driver correctly resolved to reverb',
        ];
        return 'PASS';
    }

    private function checkConfigConsistency(&$checks): string
    {
        $reverbApps = config('reverb.apps.apps');
        $broadcastingReverb = config('broadcasting.connections.reverb');

        if (!is_array($reverbApps) || count($reverbApps) === 0) {
            $checks[] = [
                'Check',
                'Config consistency (reverb.apps vs broadcasting)',
                'FAIL',
                'No apps defined in config/reverb.php apps.apps',
            ];
            return 'FAIL';
        }

        $reverbApp = $reverbApps[0];

        $reverbKey = trim((string) ($reverbApp['key'] ?? ''));
        $reverbSecret = trim((string) ($reverbApp['secret'] ?? ''));
        $reverbId = trim((string) ($reverbApp['app_id'] ?? ''));

        $broadcastKey = trim((string) ($broadcastingReverb['key'] ?? ''));
        $broadcastSecret = trim((string) ($broadcastingReverb['secret'] ?? ''));
        $broadcastId = trim((string) ($broadcastingReverb['app_id'] ?? ''));

        $mismatch = [];
        if ($reverbKey !== $broadcastKey) {
            $mismatch[] = 'key';
        }
        if ($reverbSecret !== $broadcastSecret) {
            $mismatch[] = 'secret';
        }
        if ($reverbId !== $broadcastId) {
            $mismatch[] = 'app_id';
        }

        if (count($mismatch) > 0) {
            $checks[] = [
                'Check',
                'Config consistency (reverb.apps vs broadcasting)',
                'FAIL',
                'Mismatch in: ' . implode(', ', $mismatch),
            ];
            return 'FAIL';
        }

        $checks[] = [
            'Check',
            'Config consistency (reverb.apps vs broadcasting)',
            'PASS',
            'Config values match',
        ];
        return 'PASS';
    }

    private function checkEnvVsConfigDivergence(&$checks): string
    {
        $envKey = trim((string) env('REVERB_APP_KEY'));
        $envId = trim((string) env('REVERB_APP_ID'));
        $envSecret = trim((string) env('REVERB_APP_SECRET'));

        $configKey = trim((string) config('reverb.apps.apps.0.key', ''));
        $configId = trim((string) config('reverb.apps.apps.0.app_id', ''));
        $configSecret = trim((string) config('reverb.apps.apps.0.secret', ''));

        $mismatch = [];
        if ($envKey !== $configKey) {
            $mismatch[] = 'REVERB_APP_KEY';
        }
        if ($envId !== $configId) {
            $mismatch[] = 'REVERB_APP_ID';
        }
        if ($envSecret !== $configSecret) {
            $mismatch[] = 'REVERB_APP_SECRET';
        }

        if (count($mismatch) > 0) {
            $checks[] = [
                'Check',
                'Env vs config divergence (stale cache)',
                'FAIL',
                'Divergence detected in: ' . implode(', ', $mismatch) . '. Run "php artisan config:clear"',
            ];
            return 'FAIL';
        }

        $checks[] = [
            'Check',
            'Env vs config divergence (stale cache)',
            'PASS',
            'Env and config values match',
        ];
        return 'PASS';
    }

    private function checkServerVsClientFacing(&$checks): string
    {
        $serverHost = trim((string) config('reverb.servers.reverb.host', ''));
        $serverPort = (int) config('reverb.servers.reverb.port', 8080);

        $clientHost = trim((string) config('broadcasting.connections.reverb.options.host', ''));
        $clientPort = (int) config('broadcasting.connections.reverb.options.port', 8080);

        // Warn if server bind is 0.0.0.0 but client is not localhost-ish or reverb
        if ($serverHost === '0.0.0.0' && !in_array($clientHost, ['localhost', 'reverb', '127.0.0.1'], true)) {
            $checks[] = [
                'Check',
                'Reverb server vs client-facing config',
                'WARN',
                "Server binds 0.0.0.0:{$serverPort}, client targets {$clientHost}:{$clientPort} (verify correctness)",
            ];
            return 'WARN';
        }

        $checks[] = [
            'Check',
            'Reverb server vs client-facing config',
            'PASS',
            "Server {$serverHost}:{$serverPort}, client {$clientHost}:{$clientPort}",
        ];
        return 'PASS';
    }

    private function checkAppKeyAndPosConfig(&$checks): void
    {
        $appKey = trim((string) config('app.key', ''));
        $posIp = trim((string) env('POS_IP'));

        $warnings = [];
        if ($appKey === '' || str_starts_with($appKey, 'base64:') === false) {
            $warnings[] = 'APP_KEY not properly set';
        }
        if ($posIp === '') {
            $warnings[] = 'POS_IP not configured';
        }

        if (count($warnings) > 0) {
            $checks[] = [
                'Check',
                'APP_KEY and POS config',
                'WARN',
                implode('; ', $warnings),
            ];
            return;
        }

        $checks[] = [
            'Check',
            'APP_KEY and POS config',
            'PASS',
            'Both configured',
        ];
    }

    private function printTable(array $checks): void
    {
        // Headers
        $headers = ['Check', 'Description', 'Status', 'Detail'];

        // Format rows: status determines color/symbol
        $rows = [];
        foreach ($checks as $check) {
            $rows[] = [
                $check[0],
                $check[1],
                $check[2],
                $check[3],
            ];
        }

        $this->table($headers, $rows);
    }
}
