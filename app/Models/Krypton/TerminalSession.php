<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TerminalSession extends Model
{
    protected $connection = 'pos';
    protected $table = 'terminal_sessions';
    protected $primaryKey = 'id';
    
}
