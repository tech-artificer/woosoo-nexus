<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    protected $connection = 'pos';
    protected $table = 'tables';
    protected $primaryKey = 'id';

    public $timestamps = false;

    public function tableOrders() : HasMany
    {
        return $this->hasMany(TableOrder::class);
    }


    public function changeTableStatus() {
        return Table::fromQuery('CALL change_table_status_of_current_table(?)', [$this->id]);
    }

}
