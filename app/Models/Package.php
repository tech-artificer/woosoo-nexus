<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'krypton_menu_id',
        'base_price',
        'min_meat',
        'max_meat',
        'min_side',
        'max_side',
        'min_dessert',
        'max_dessert',
        'min_beverage',
        'max_beverage',
        'banner_media_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'krypton_menu_id' => 'integer',
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
        return $this->hasMany(PackageAllowedMenu::class)->orderBy('sort_order');
    }

    public function activeAllowedMenus(): HasMany
    {
        return $this->allowedMenus()->where('is_active', true);
    }
}
