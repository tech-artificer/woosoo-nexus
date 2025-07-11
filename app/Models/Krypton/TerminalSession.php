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

    protected $fillable = [
        'id',
        'date_time_opened',
        'terminal_id',
        'terminal_session_id',
        'session_id',
        'date_time_closed',
        'previous_sale',
        'current_sale',
        'accumulated_sale',
        'transaction_count',
        'previous_sale_template',
        'current_sale_template',
        'accumulated_sale_template',
    ];

    // public function scopeCurrent(Builder $query) {
    //     return $query->whereDate('date_time_opened', today())->whereNull('date_time_closed');
    // }
}
