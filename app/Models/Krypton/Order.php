<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;
   
    protected $connection = 'pos';
    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'is_open' => 'boolean',
        'is_transferred' => 'boolean',
        'is_voided' => 'boolean',
        'is_available' => 'boolean',
        'is_online_order' => 'boolean',
    ];

    protected $dates = [
        'date_time_opened',
        'date_time_closed',
    ];

    // protected $fillable = [
    //     'session_id', 
    //     'terminal_session_id', 
    //     'date_time_opened', 
    //     'date_time_closed',
    //     'revenue_id', 
    //     'terminal_id', 
    //     'current_terminal_id',
    //     'customer_id',
    //     'is_open',
    //     'is_transferred',
    //     'is_voided',
    //     'guest_count',
    //     'service_type_id',
    //     'start_employee_log_id',
    //     'current_employee_log_id',
    //     'close_employee_log_id',
    //     'server_employee_log_id',
    //     'transaction_no',
    //     'reference',
    //     'cashier_employee_id',
    //     'terminal_service_id',
    //     'is_online_order',
    // ];

    // public function employeeLogs() : BelongsTo
    // {
    //     return $this->belongsTo(EmployeeLog::class, 'start_employee_log_id', 'id')
    //                 ->with(['current', 'close', 'server']);
    // }

    public function revenue() : BelongsTo
    {
        return $this->belongsTo(Revenue::class, 'revenue_id');
    }

    public function terminal() : BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }


    public function tableOrders() : HasMany
    {
        return $this->hasMany(TableOrder::class, 'order_id');
    }

    public function orderChecks() : HasMany
    {
        return $this->hasMany(OrderCheck::class, 'order_id');
    }

    public function orderedMenus() : HasMany
    {
        return $this->hasMany(OrderedMenu::class, 'order_id');
    }
}
