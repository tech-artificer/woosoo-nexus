<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enums\TableStatus; // Assuming you have an enum for table statuses
use App\Models\Device;
use App\Services\Krypton\KryptonContextService;


class Table extends Model
{
    use HasFactory;

    protected $connection = 'pos';
    protected $table = 'tables';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'is_available' => 'boolean',
        'is_locked' => 'boolean',
        'status' => TableStatus::class, // Assuming you have an enum for table statuses
    ];

    protected $hidden = [
        'position_x',
        'position_y',
        'z_index',
        'rotation',
        'merchant_id',
        'employee_name',
        'order_created_in',
        'created_on',
        'modified_on',
    ];

    // Relationships
    public function tableOrders() : HasMany
    {
        return $this->hasMany(TableOrder::class,'table_id');
    }

    // Optional: relationship (if needed)
    public function device() : HasOne
    {
        return $this->hasOne(Device::class, 'table_id');
    }

    public function checkTableStatus()
    {
        $contextService = new KryptonContextService();
        $currentSessions = $contextService->getCurrentSessions();
        $session = $currentSessions['session'];


        return Table::fromQuery('CALL check_table_status(?,?)', [$this->id, $session->id]);
    }
}
