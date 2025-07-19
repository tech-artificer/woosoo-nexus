<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class Revenue extends Model
{
    protected $connection = 'pos';
     protected $table = 'revenues';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $dates = [
        'date_time',
    ];
}
