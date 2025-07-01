<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\MenuImage;
use App\Repositories\Krypton\MenuRepository;

class Menu extends Model
{
    protected $connection = 'pos';
    protected $table = 'menus';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',  
        'menu_group_id', 
        'menu_tax_type_id',
        'menu_category_id',
        'menu_course_type_id',
        'name',
        'kitchen_name',
        'receipt_name',
        'price',
        'cost'
    ];

    protected $casts = [
        'menu_category_id' => 'integer',
        'menu_group_id' => 'integer',
        'menu_course_type_id' => 'integer',
        'menu_id' => 'integer',
        'price' => 'decimal:2',
    ];

    public function modifiers() : HasMany
    {
        return $this->hasMany(self::class, 'id') // dummy just to use eager loading
            ->whereRaw('1 = 0'); // always empty, we will override it
    }

    public function category() : BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function group() : BelongsTo
    {
        return $this->belongsTo(MenuGroup::class, 'menu_group_id');
    }

    public function course() : BelongsTo
    {
        return $this->belongsTo(MenuCourse::class, 'menu_course_type_id', 'id');
    }

    public function image() : HasOne
    {
        return $this->hasOne(MenuImage::class, 'menu_id', 'id');
    }

    # SCOPES
    //  public function scopeFilter(Builder $query, array $filters)
    // {
    //     if (!empty($filters['menu_category_id'])) {
    //         $query->where('menu_category_id', $filters['menu_category_id']);
    //     }

    //     if (!empty($filters['menu_course_type_id'])) {
    //         $query->where('menu_course_type_id', $filters['menu_course_type_id']);
    //     }

    //     if (!empty($filters['menu_group_id'])) {
    //         $query->where('menu_group_id', $filters['menu_group_id']);
    //     }

    //     if (!empty($filters['search'])) {
    //         $query->where('name', 'like', '%' . $filters['search'] . '%');
    //     }

    //     return $query;
    // }

    // public function scopeAvailable(Builder $query)
    // {
    //     return $query->where('is_available', 1);
    // }


    public function scopeSetMeals($query)
    {
        return $query->where('menu_category_id', 1);
    }

    // public function scopeSetModifiers($query)
    // {
    //     return $query->where(['index' => NULL, 'is_modifier' => 1,'price' => 0]);
    // }

    // public function scopeNonModifiers($query)
    // {
    //     return $query->where(['is_modifier' => 0]);
    // }
    //  public function scopePriced($query)
    // {
    //     return $query->where('price', '>', 0);
    // }

    public static function getModifiers(int $id) {
        
        $codes = [
            46 => ['P1', 'P2', 'P3', 'P4', 'P5'],
            47 => ['P1', 'P2', 'P3', 'P4', 'P5', 'B1', 'B2', 'B3'],
            48 => [
                'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8', 'P9',
                'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9', 'B10',
                'C1',
            ],
        ];

        return Self::whereIn('receipt_name', $codes[$id])->get();
    }
}
