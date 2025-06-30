<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class TransactionNo extends Model
{
    protected $connection = 'pos';
    protected $table = 'transaction_no';
    protected $primaryKey = 'id';


    public $timestamps = false;

    protected $casts = [
        'created_on' => 'datetime',
        'modified_on' => 'datetime',
    ];

}
