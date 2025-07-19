<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;


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
     *
     * @return string
     */
    public static function getLatestSession() {
      return Self::fromQuery('CALL get_latest_session()');
    } 

    /**
     * Get the latest session ID.
     * 
     * @return string
     */
    public static function getLatestSessionId() {
      return Self::fromQuery('CALL get_latest_session_id()');
    } 

  }
