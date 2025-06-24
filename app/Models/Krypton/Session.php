<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

use App\Repositories\Krypton\SessionRepository;


class Session extends Model
{   
    protected $connection = 'pos';
    protected $table = 'sessions';

    protected $casts = [
      'id' => 'integer',  
    ];

    protected $sessionRepository;

    public function __construct() {
      $this->sessionRepository = new SessionRepository();
      $this->id = $this->sessionRepository->getLatestSessionId();
    }

  }
