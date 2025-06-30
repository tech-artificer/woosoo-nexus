<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TerminalSession extends Model
{

    protected $connection = 'pos';
    protected $table = 'terminal_sessions';
    protected $primaryKey = 'id';

    public $timestamps = false; 

    protected $casts = [
        'date_time_opened' => 'datetime',
        'date_time_closed' => 'datetime',
        'created_on' => 'datetime',
    ];

    public function scopeCurrent(Builder $query) {
        return $query->whereDate('date_time_opened', today())->whereNull('date_time_closed');
    }
    
}
