<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CashTraySession extends Model
{
    protected $connection = 'pos';
    protected $table = 'cash_tray_sessions';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;

}
