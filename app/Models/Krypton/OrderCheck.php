<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderCheck extends Model
{
    use HasFactory;

    protected $connection = 'pos';
    protected $table = 'order_checks';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $casts = [
        'is_voided' => 'boolean',
        'is_settled' => 'boolean',
        'from_split' => 'boolean',
    ];

   protected $fillable = [
        'order_id',
        'total_amount',
        'paid_amount',
        'tax_amount',
        'discount_amount',
        'is_voided',
        'is_settled',
        'from_split',
    ];

    // Relationships
    public function order() : BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function appliedTaxes() : HasMany
    {
        return $this->hasMany(AppliedTax::class, 'order_check_id');
    }

    public function appliedDiscounts() : HasMany
    {
        return $this->hasMany(AppliedDiscount::class, 'order_check_id');
    }

}