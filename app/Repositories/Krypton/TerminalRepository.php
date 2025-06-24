<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;

class TerminalRepository
{   

    protected $connection = 'pos';
    public static function getLatestSessionId()
    {
        try {
            return DB::connection($this->connection)->select('CALL get_latest_session_id()');
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Failed to fetch latest session id.');
        }
    }

    public static function getActiveCashTrayBySession($sessionId)
    {
        try {
            return DB::connection($this->connection)->select('CALL get_active_cash_tray_by_session(?)', [$sessionId]);
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Failed to fetch latest session id.');
        }
    }
}