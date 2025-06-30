<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableGroup extends Model
{
    protected $connection = 'pos';
    protected $table = 'table_groups';
    protected $primaryKey = 'id';

    public $timestamps = false;

    public function tables() : HasMany
    {
        return $this->hasMany(Table::class);
    }
}
