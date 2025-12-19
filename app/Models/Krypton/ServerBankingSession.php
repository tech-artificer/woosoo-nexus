<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

/**
 * ServerBankingSession model for Krypton POS database (legacy system)
 * 
 * Read-only integration with Krypton POS `krypton_woosoo` database.
 * POS tables do not include created_at/updated_at timestamps.
 */
class ServerBankingSession extends Model
{
    protected $connection = 'pos';
    protected $table = 'server_banking_sessions';
    protected $primaryKey = 'id';
    public $timestamps = false; // POS DB tables have no timestamp columns
}
