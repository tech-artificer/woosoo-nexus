<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableType extends Model
{
    protected $connection = 'pos';
    protected $table = 'table_type';
    protected $primaryKey = 'id';

    public $timestamps = false;


    public function tables() : HasMany
    {
        return $this->hasMany(Table::class);
    }

}
