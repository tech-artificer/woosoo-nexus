<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderedMenu extends Model
{
    use HasFactory;

    protected $connection = 'pos';
    protected $table = 'ordered_menus';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'menu_id',
        'quantity',
        'price',
        'is_voided',
        'void_reason',
        'employee_log_id',
    ];

    protected $casts = [
        'is_voided' => 'boolean',
    ];

    public function order() : BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function menu() : BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function employeeLog() : BelongsTo
    {
        return $this->belongsTo(EmployeeLog::class, 'employee_log_id');
    }
   
}
