<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class TerminalService extends Model
{
    protected $connection = 'pos';
    protected $table = 'terminal_services';
    protected $primaryKey = 'id';
    public $timestamps = false;
    
}
