<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    protected $connection = 'pos';
    protected $table = 'menu_categories';
    protected $primaryKey = 'id';
    
    protected $casts = [
        'id' => 'integer',
    ];

    public function menus() : HasMany
    {
        return $this->hasMany(Menu::class, 'menu_category_id', 'id');
    }

    public function groups() : HasMany
    {
        return $this->hasMany(Menu::class);
    }

}
