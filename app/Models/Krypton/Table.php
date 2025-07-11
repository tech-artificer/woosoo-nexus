<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\TableStatus; // Assuming you have an enum for table statuses

class Table extends Model
{
    use HasFactory;

    protected $connection = 'pos';
    protected $table = 'tables';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $casts = [
        'is_available' => 'boolean',
        'is_locked' => 'boolean',
        'status' => TableStatus::class, // Assuming you have an enum for table statuses
    ];

    // Relationships
    public function tableOrders() : HasMany
    {
        return $this->hasMany(TableOrder::class,'table_id');
    }
}
