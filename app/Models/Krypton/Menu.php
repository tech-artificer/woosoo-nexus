<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\MenuImage;
use Illuminate\Support\Number;
use Illuminate\Support\Facades\Storage;

class Menu extends Model
{
    use HasFactory;

    protected $connection = 'pos';
    protected $table = 'menus';
    protected $primaryKey = 'id';
    protected $guarded = [];
    protected $appends = ['computed_modifiers'];
    public $timestamps = false;

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_available' => 'boolean',
        'is_modifier' => 'boolean',
        'is_discountable' => 'boolean',
        'is_locked' => 'boolean',
        'is_modifier_only' => 'boolean',
        'menu_category_id' => 'integer',
        'menu_group_id' => 'integer',
        'menu_tax_type_id' => 'integer',
        'menu_course_type_id' => 'integer',
        'price' => 'decimal:2',
    ];

    protected $hidden = [
        'created_on',
        'modified_on',
    ];

    public function modifiers()
    {
        return $this->hasMany(Menu::class, 'id')->whereRaw('1 = 0');
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

    public function menuImage(): HasOne
    {
        return $this->hasOne(MenuImage::class, 'menu_id');
    }

    public function image(): HasOne
    {
        return $this->hasOne(MenuImage::class, 'menu_id');
    }


    public function orderedMenus() : HasMany
    {
        return $this->hasMany(OrderedMenu::class, 'menu_id');
    }

    public function tax() : BelongsTo
    {
        return $this->belongsTo(Tax::class, 'menu_tax_type_id');
    }

    public function taxComputation($quantity) {

        if ( !$this->is_taxable || $quantity == 0 || !$this->tax ) {
            return 0;
        }

        $percentage = $this->tax->percentage ?? 0;
        $decimals = (int)$this->tax->rounding ?? 0;
        $taxAmount = ($this->price * $quantity) * ($percentage / 100);

        return Number::format($taxAmount, $decimals);
    }

    public function getImageUrlAttribute()
    {
        $imgPath = MenuImage::where('menu_id', $this->id)->first()->path ?? null;

        if ($imgPath) {
            return Storage::disk('public')->url($imgPath);
        }

        return asset('images/menu-placeholder/1.jpg');
    }

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

        return Menu::with(['image'])->whereIn('receipt_name', $codes[$id])->where('is_modifier_only', true)->get();
    }

    public function getComputedModifiersAttribute()
    {
        if (!in_array($this->id, [46, 47, 48])) {
            return collect(); // Return empty collection if not a "package" menu
        }

        $codes = [
            46 => ['P1', 'P2', 'P3', 'P4', 'P5'],
            47 => ['P1', 'P2', 'P3', 'P4', 'P5', 'B1', 'B2', 'B3'],
            48 => [
                'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8', 'P9',
                'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9', 'B10',
                'C1',
            ],
        ];

        return Menu::with(['image'])->whereIn('receipt_name', $codes[$this->id])
            ->whereHas('group', function ($query) {
                $query->where('name', 'Meat Order');
            })
            ->get();
    }

    public function loadModifiers() {
    
        $modifiers = $this->getComputedModifiersAttribute();

        if( $modifiers->isEmpty() ) {
            $modifiers = $this->modifiers;
        }

        return $modifiers ?? [];
    }
}
