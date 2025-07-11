<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLog extends Model
{
    protected $connection = 'pos';
    protected $table = 'employee_logs';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'employee_id', 
        'terminal_id', 
        'shift_id', 
        'start_time', 
        'end_time', 
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function getEmployeeLogsForSession($sessionId)
    {
        return Self::fromQuery("CALL get_employee_logs_for_session(?)", [$sessionId]);
    }

    public static function getEmployeeLog($logId)
    {
        return Self::fromQuery("CALL get_employee_log(?)", [$logId]);
    }

    protected $dates = [
        'start_time', 'end_time',
    ];

    public function employee() : BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function terminal() : BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

}
