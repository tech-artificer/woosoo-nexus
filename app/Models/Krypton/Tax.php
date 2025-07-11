<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $connection = 'pos';
    protected $table = 'taxes';
    protected $primaryKey = 'id';
    public $timestamps = false;

}
