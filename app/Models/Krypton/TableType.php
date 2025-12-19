<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TableType model for Krypton POS database (legacy system)
 * 
 * Read-only integration with Krypton POS `krypton_woosoo` database.
 * POS tables do not include created_at/updated_at timestamps.
 */
class TableType extends Model
{
    protected $connection = 'pos';
    protected $table = 'table_type';
    protected $primaryKey = 'id';
    public $timestamps = false; // POS DB tables have no timestamp columns

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }
}
