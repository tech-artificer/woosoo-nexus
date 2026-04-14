<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TabletPackageConfig extends Model
{
    use HasFactory;

    protected $table = 'tablet_package_configs';

    protected $fillable = [
        'name', 'description', 'base_price',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function allowedMenus(): HasMany
    {
        return $this->hasMany(TabletPackageAllowedMenu::class, 'package_config_id');
    }

    public function activeAllowedMenus(): HasMany
    {
        return $this->allowedMenus()->where('is_active', true);
    }
}
