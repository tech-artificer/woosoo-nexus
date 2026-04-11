<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabletPackageAllowedMenu extends Model
{
    protected $table = 'tablet_package_allowed_menus';

    protected $fillable = [
        'package_config_id',
        'krypton_menu_id',
        'menu_type',
        'min_qty',
        'max_qty',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'min_qty'    => 'integer',
        'max_qty'    => 'integer',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(TabletPackageConfig::class, 'package_config_id');
    }
}
