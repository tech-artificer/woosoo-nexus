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

            $current = Config::get('database.connections.pos', []);

            $override = array_filter([
                'host'     => $creds['host'],
                'port'     => $creds['port'],
                'database' => $creds['database'],
                'username' => $creds['username'],
                'password' => $creds['password'] ?? '',
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
            Log::warning('[PosConnectionService] Failed to apply POS connection from DB: ' . $e->getMessage());
        }
    }

    /**
     * Test a set of credentials WITHOUT persisting them.
     * Returns ['success' => bool, 'message' => string].
     */
    public function testCredentials(string $host, string $port, string $database, string $username, string $password): array
    {
        $tempName = 'pos_probe_' . uniqid();

        try {
            Config::set("database.connections.{$tempName}", [
                'driver'    => 'mysql',
                'host'      => $host,
                'port'      => (int) $port,
                'database'  => $database,
                'username'  => $username,
                'password'  => $password,
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
                'strict'    => true,
                'options'   => [
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
