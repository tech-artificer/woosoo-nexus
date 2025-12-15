<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;
use App\Models\Krypton\Session;

class SessionRepository
{
    protected $connection = 'pos';

    public static function getLatestSession()
    {
        try {
            if (app()->environment('testing') || env('APP_ENV') === 'testing') {
                return Session::orderByDesc('id')->first();
            }

            return Session::fromQuery('CALL get_latest_session()');
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

}