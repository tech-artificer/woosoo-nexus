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
        'min_meat', 'max_meat',
        'min_side', 'max_side',
        'min_dessert', 'max_dessert',
        'min_beverage', 'max_beverage',
        'banner_media_id',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'min_meat' => 'integer',
        'max_meat' => 'integer',
        'min_side' => 'integer',
        'max_side' => 'integer',
        'min_dessert' => 'integer',
        'max_dessert' => 'integer',
        'min_beverage' => 'integer',
        'max_beverage' => 'integer',
        'banner_media_id' => 'integer',
        'is_active' => 'boolean',
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
