<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;


class Session extends Model
{   
    protected $connection = 'pos';
    protected $table = 'sessions';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
      'date_time_opened',
      'date_time_closed',
      'created_on',
      'modified_on',
    ];

    /**
     * Get the latest session.
     * Returns null if unable to query POS database (graceful fallback).
     *
     * @return self|null
     */
    public static function getLatestSession() {
      try {
        return self::fromQuery('CALL get_latest_session()')->first();
      } catch (\Throwable $e) {
        Log::warning('POS getLatestSession query failed', ['error' => $e->getMessage()]);
        return null; // Graceful fallback when POS is unavailable
      }
    } 

    /**
     * Get the latest session ID.
     * Returns null if unable to query POS database (graceful fallback).
     * 
     * @return mixed
     */
    public static function getLatestSessionId() {
      try {
        return self::fromQuery('CALL get_latest_session_id()')->first();
      } catch (\Throwable $e) {
        Log::warning('POS getLatestSessionId query failed', ['error' => $e->getMessage()]);
        return null; // Graceful fallback when POS is unavailable
      }
    } 

  }
