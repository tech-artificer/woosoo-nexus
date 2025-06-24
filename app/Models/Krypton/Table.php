<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    protected $connection = 'pos';
    protected $table = 'tables';
    protected $primaryKey = 'id';

    public $timestamps = false;

}
