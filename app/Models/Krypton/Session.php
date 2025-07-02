<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

use App\Repositories\Krypton\SessionRepository;


class Session extends Model
{   
    protected $connection = 'pos';
    protected $table = 'sessions';

    public $timestamps = false;
    protected $fillable = [
      'date_time_opened',
      'date_time_closed',
      'created_on',
      'modified_on',
    ];

    protected $casts = [
      'id' => 'integer',
      'date_time_opened' => 'datetime',
      'date_time_closed' => 'datetime',
  ];

    // protected $sessionRepository;

    // public function __construct() {
    //   $this->sessionRepository = new SessionRepository();
    //   $this->id = $this->sessionRepository->getLatestSessionId();
    // }

    public static function getLatestSession() {
      return SessionRepository::getLatestSession();
    } 

  }
