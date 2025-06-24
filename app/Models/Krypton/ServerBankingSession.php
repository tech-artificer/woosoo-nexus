<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class ServerBankingSession extends Model
{
    protected $connection = 'pos';
    protected $table = 'server_banking_sessions';
    protected $primaryKey = 'id';
}
