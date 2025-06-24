<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;

class SessionRepository
{
    protected $connection = 'pos';

    public function getLatestSessionId()
    {
        try {
            return DB::connection($this->connection)->select('CALL get_latest_session_id()');
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

}