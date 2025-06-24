<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Orders extends Model
{
    protected $connection = 'pos';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders';

    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'session_id',
        'terminal_session_id',
        'date_time_opened',
        'date_time_closed',
        'revenue_id',
        'terminal_id',
        'customer_id',
        'current_terminal_id',
        'end_terminal_id',
        'customer_id',
        'is_open',
        'is_transferred',
        'is_voided',
        'guest_count',
        'service_type_id',
        // 'is_available',
        // 'cash_tray_session_id',
        // 'server_banking_session_id',
        'start_employee_log_id',
        'current_employee_log_id',
        'close_employee_log_id',
        'server_employee_log_id',
        // 'transaction_no',
        'reference',
        'cashier_employee_id',
        'terminal_service_id',
        'is_online_order',
        // 'reprint_count'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'session_id' => 'integer',
        'terminal_session_id' => 'integer',
        'revenue_id' => 'integer',
        'terminal_id' => 'integer',
        'service_type_id' => 'integer',
        'cash_tray_session_id' => 'integer',
        'server_banking_session_id' => 'integer',
        'start_employee_log_id' => 'integer',
        'current_employee_log_id' => 'integer',
        'close_employee_log_id' => 'integer',
        'server_employee_log_id' => 'integer',
        'transaction_no' => 'integer',
        'cashier_employee_id' => 'integer',
        'terminal_service_id' => 'integer',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_on',
        'modified_on'
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function orderedMenus() : HasMany
    {
        return $this->hasMany(OrderedMenu::class, 'order_id');
    }


    protected static function boot() : void
    {
        parent::boot();

        static::creating(function ($model) {
            
            // $terminal = Terminal::posTerminal()->get();
            // $terminalSession = TerminalSession::where('terminal_id', $terminal->id)->first();
            // $terminalService = TerminalService::where('terminal_id', $terminal->id)->first();
            // $employeeLog = EmployeeLog::today()->where('session_id', $terminalSession->id)->first();
            // $transactionCount = Self::where('session_id', $terminalSession->id)->count();

            // $model->session_id = $terminalSession->id;
            // $model->terminal_session_id = $terminalSession->id;
            // $model->date_time_opened = now();
            // $model->revenue_id = $terminalService->revenue_id;
            // $model->terminal_id = $terminal->id; // $terminalService->terminal_id;
            // $model->is_open = 1; // 1 or 0
            // $model->is_transferred = 0; // 1 or 0
            // $model->is_voided = 0; // 1 or 0
            // $model->guest_count = 1; // Default guest count
            // $model->service_type_id = $terminalService->service_type_id;
            // $model->start_employee_log_id = $employeeLog->id;
            // $model->transaction_no = $transactionCount++;
            // $model->is_available = 1;
            // $model->date_time_closed = null;
            // $model->end_terminal_id  = null;
            // $model->customer_id = null;
            // $model->reference = null;
            // $model->cashier_employee_id = null;
            // $model->current_terminal_id = $terminal->id;
            // $model->current_employee_log_id = $employeeLog->id;    
            // $model->cash_tray_session_id = null; // Initially set to DEFAULT NULL
            // $model->server_banking_session_id = null; // Initially set to DEFAULT NULL
            // $model->server_employee_log_id = null; // Initially set to DEFAULT NULL
            // $model->close_employee_log_id = null; // Initially set to DEFAULT NULL
            // $model->terminal_service_id = 1; // By Default - DINE IN only terminal_services Table
            // $model->is_online_order = 1; // Initially set to DEFAULT 1
            // $model->reprint_count = 0; // Initially set to DEFAULT 0
        });

      
    }
}
