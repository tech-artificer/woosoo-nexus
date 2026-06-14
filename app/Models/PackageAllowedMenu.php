<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageAllowedMenu extends Model
{
    protected $table = 'package_allowed_menus';

    protected $fillable = [
        'package_id',
        'krypton_menu_id',
        'menu_type',
        'meat_category_code',
        'extra_price',
        'quantity_limit',
        'is_required',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'krypton_menu_id' => 'integer',
        'extra_price' => 'decimal:2',
        'quantity_limit' => 'integer',
        'is_required' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
