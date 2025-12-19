<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;
use App\Models\Krypton\Session;
use Illuminate\Support\Facades\Log;

class SessionRepository
{
    protected $connection = 'pos';

    public static function getLatestSession()
    {
        try {
            if (app()->environment('testing') || env('APP_ENV') === 'testing') {
                return Session::orderByDesc('id')->first();
            }

            return Session::fromQuery('CALL get_latest_session()')->first();
        } catch (\Throwable $e) {
            Log::warning('POS get_latest_session procedure failed (graceful fallback)', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            // Return null instead of throwing - allows app to continue with degraded POS functionality
            return null;
        }
    }

}