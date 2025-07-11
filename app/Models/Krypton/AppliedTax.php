<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppliedTax extends Model
{
    protected $connection = 'pos';
    protected $table = 'applied_taxes';
    protected $primaryKey = 'id';
    public $timestamps = false;

   public function orderCheck() : BelongsTo
   {
       return $this->belongsTo(OrderCheck::class, 'order_check_id');
   }
}
