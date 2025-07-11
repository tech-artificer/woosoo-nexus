<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use HasFactory;

    protected $connection = 'pos';
    protected $table = 'menu_categories';
    protected $guarded = [];
    public $timestamps = false;
    


    public function menus() : HasMany
    {
        return $this->hasMany(Menu::class, 'menu_category_id');
    }

    public function groups() : HasMany
    {
        return $this->hasMany(Menu::class);
    }

}
