<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class TransactionNo extends Model
{
    protected $connection = 'pos';
    protected $table = 'transaction_no';
    public $timestamps = false;
}
