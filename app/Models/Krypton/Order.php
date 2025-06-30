<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Models\Krypton\OrderCheck;
use App\Models\Krypton\OrderedMenu;

class Order extends Model
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
        'id',
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
        'is_available',
        'cash_tray_session_id',
        'server_banking_session_id',
        'start_employee_log_id',
        'current_employee_log_id',
        'close_employee_log_id',
        'server_employee_log_id',
        'transaction_no',
        'reference',
        'cashier_employee_id',
        'terminal_service_id',
        'is_online_order',
        'reprint_count'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
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

    public function orderCheck() : HasOne
    {
        return $this->hasOne(OrderCheck::class, 'order_id');
    }

    public function getTransactionNo($sessionId) {
        return Order::where(['session_id' => $sessionId])->count() + 1;
    }

    public function createOrder() {

        $orderDetails = $this->toArray(); 

        $numberOfParameters = count($orderDetails);
        // Create an array of '?' strings, one for each parameter.
        $placeholdersArray = array_fill(0, $numberOfParameters, '?');
        // Join them with a comma and space to form the placeholder string.
        $placeholders = implode(', ', $placeholdersArray);
        // 2. Extract Values
        // array_values() extracts all the values from the associative array
        // and returns them as a new numerically indexed array.
        $params = array_values($orderDetails);

        // Now, call your fromQuery method with the generated placeholders and parameters
        return Order::fromQuery('CALL create_order(' . $placeholders . ')', $params);
    }
}
