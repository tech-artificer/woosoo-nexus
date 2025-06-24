<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLog extends Model
{
    protected $connection = 'pos';
    protected $table = 'employee_logs';
    protected $primaryKey = 'id';

     protected $casts = [
        'id' => 'integer',  
    ];
    
    public function employee() : BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function scopeToday($query, $session_id)
    {
        return $query->whereDate('start_time', today())->where('session_id', $session_id);
    }


}
