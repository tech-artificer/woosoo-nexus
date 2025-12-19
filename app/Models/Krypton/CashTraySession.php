<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * CashTraySession model for Krypton POS database (legacy system)
 * 
 * Read-only integration with Krypton POS `krypton_woosoo` database.
 * POS tables do not include created_at/updated_at timestamps.
 */
class CashTraySession extends Model
{
    protected $connection = 'pos';
    protected $table = 'cash_tray_sessions';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false; // POS DB tables have no timestamp columns
}
