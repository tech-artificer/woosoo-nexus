<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PosConnectionService
{
    /**
     * Apply DB-stored POS credentials to the running Laravel 'pos' connection.
     * Called from AppServiceProvider::boot() on every request.
     * Silently skips when the table is not yet migrated.
     */
    public function applyFromDatabase(): void
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return;
            }

            if (! SystemSetting::hasPosConnection()) {
                return; // Not configured yet — fall back to .env defaults
            }

            $creds = SystemSetting::getPosConnection();

            if (blank($creds['password'] ?? null)) {
                Log::warning('[PosConnectionService] POS host/database/username are configured but the password is empty — connections will fail with access denied');
            }

            $current = Config::get('database.connections.pos', []);

            $override = array_filter([
                'host' => $creds['host'],
                'port' => $creds['port'],
                'database' => $creds['database'],
                'username' => $creds['username'],
                // Only override password when DB has a real value; otherwise
                // the .env fallback (DB_POS_PASSWORD) is used.
                'password' => filled($creds['password'] ?? null) ? $creds['password'] : null,
            ], fn ($v) => $v !== null);

            // Only purge and reconfigure if credentials actually differ from
            // what is already active. Purging on every request would destroy
            // performance under concurrent PHP-FPM workers.
            $changed = $current['host'] !== ($override['host'] ?? null)
                || $current['database'] !== ($override['database'] ?? null)
                || $current['username'] !== ($override['username'] ?? null)
                || $current['password'] !== ($override['password'] ?? null);

            Config::set('database.connections.pos', array_merge($current, $override));

            if ($changed) {
                DB::purge('pos');
            }
        } catch (\Throwable $e) {
            Log::warning('[PosConnectionService] Failed to apply POS connection from DB: '.$e->getMessage());
        }
    }

    /**
     * @return array{connected: bool, status: string, message: string}
     */
    public function posStatus(): array
    {
        return once(function (): array {
            if (! $this->hasPasswordConfigured()) {
                return [
                    'connected' => false,
                    'status' => 'not_configured',
                    'message' => 'POS password is not configured. Open Configuration → POS Connection and enter the database password.',
                ];
            }

            try {
                DB::connection('pos')->getPdo();

                return [
                    'connected' => true,
                    'status' => 'connected',
                    'message' => '',
                ];
            } catch (\Throwable $e) {
                return $this->failureFromThrowable($e);
            }
        });
    }

    public function isReachable(): bool
    {
        return $this->posStatus()['connected'];
    }

    /**
     * @return array{connected: bool, status: string, message: string}
     */
    public function failureFromThrowable(\Throwable $e): array
    {
        $message = $e->getMessage();

        if (str_contains($message, '1045') || str_contains($message, 'Access denied')) {
            return [
                'connected' => false,
                'status' => 'auth_failed',
                'message' => 'POS database rejected the credentials. Check Configuration → POS Connection and verify the username and password.',
            ];
        }

        if (
            str_contains($message, '2002')
            || str_contains($message, '2003')
            || str_contains($message, 'Connection refused')
            || str_contains($message, 'timed out')
            || str_contains($message, 'getaddrinfo')
        ) {
            return [
                'connected' => false,
                'status' => 'unreachable',
                'message' => 'Unable to reach the POS database. Confirm the host and port are correct and the MySQL server is running.',
            ];
        }

        return [
            'connected' => false,
            'status' => 'unreachable',
            'message' => 'Unable to reach the POS system. Check Configuration → POS Connection.',
        ];
    }

    private function hasPasswordConfigured(): bool
    {
        if (Config::get('database.connections.pos.driver') === 'sqlite') {
            return true;
        }

        try {
            if (Schema::hasTable('system_settings') && SystemSetting::hasPosConnection()) {
                $password = SystemSetting::getPosConnection()['password'] ?? null;

                return filled($password);
            }
        } catch (\Throwable) {
            // Fall through to env-based check.
        }

        return filled(Config::get('database.connections.pos.password'));
    }

    /**
     * Test a set of credentials WITHOUT persisting them.
     * Returns ['success' => bool, 'message' => string].
     */
    public function testCredentials(string $host, string $port, string $database, string $username, string $password): array
    {
        $tempName = 'pos_probe_'.uniqid();

        try {
            Config::set("database.connections.{$tempName}", [
                'driver' => 'mysql',
                'host' => $host,
                'port' => (int) $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'options' => [
                    \PDO::ATTR_TIMEOUT => 5, // 5-second connect timeout
                ],
            ]);

            DB::connection($tempName)->getPdo();

            return ['success' => true, 'message' => "Connected to `{$database}` on {$host}:{$port}."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } finally {
            DB::purge($tempName);
            Config::set("database.connections.{$tempName}", null);
        }
    }
}
