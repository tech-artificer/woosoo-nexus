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
use Illuminate\Support\Collection;
use App\Http\Resources\MenuModifierResource;

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
            return url('storage/'.$imgPath);
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

        if (! isset($codes[$id]) || empty($codes[$id])) {
            return collect();
        }

        $codeList = $codes[$id];
        $orderExpression = "FIELD(receipt_name, '" . implode("','", $codeList) . "')";

        return Menu::with(['image'])
            ->whereIn('receipt_name', $codeList)
            ->where('is_modifier_only', true)
            ->orderByRaw($orderExpression)
            ->get();
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

        if (! isset($codes[$this->id]) || empty($codes[$this->id])) {
            return collect();
        }

        $codeList = $codes[$this->id];
        $orderExpression = "FIELD(receipt_name, '" . implode("','", $codeList) . "')";

        // Return modifier menus matching the receipt codes for this package.
        // Do not restrict by group name so the set meal modifiers include the
        // same menu rows/fields as the regular modifiers endpoint. Preserve
        // the defined code order using FIELD(...).
        return Menu::with(['image'])
            ->whereIn('receipt_name', $codeList)
            ->where('is_modifier_only', true)
            ->orderByRaw($orderExpression)
            ->get();
    }

    /**
     * Given an array/collection of stored-proc menu rows (each having `receipt_name`),
     * return package menus (46,47,48) with `modifiers` arrays matched from the
     * provided rows, preserving the defined code order and falling back to local
     * menu records when the raw rows are missing.
     *
     * @param array|\Illuminate\Support\Collection $menuRows
     * @return \Illuminate\Support\Collection
     */
    public static function getPackagesWithModifiers($menuRows = [])
    {
        // Ensure $menuRows is a collection to avoid undefined variable errors
        $menuRows = collect($menuRows ?? []);

        $codes = [
            46 => ['P1', 'P2', 'P3', 'P4', 'P5'],
            47 => ['P1', 'P2', 'P3', 'P4', 'P5', 'B1', 'B2', 'B3'],
            48 => [
                'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8', 'P9',
                'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9', 'B10',
                'C1',
            ],
        ];

        // Fetch package menu models (if present locally)
        $packageIds = array_keys($codes);
        $packages = Menu::with(['image'])->whereIn('id', $packageIds)->get()->keyBy('id');

        // Flatten all codes to prefetch modifier menu models as fallback and
        // normalize to uppercase to avoid case-mismatch between POS rows
        // and local DB values.
        $allCodes = array_values(array_unique(array_map('strtoupper', array_merge(...array_values($codes)))));

        // Restrict modifiers to the 'Meat Order' group (same as controller
        // `getMenuModifiers`) to ensure the package modifiers are selected
        // from the same set the /menus/modifiers endpoint returns. Key the
        // resulting collection by UPPERCASE receipt_name for reliable lookup.
        $modifierModels = Menu::with(['image', 'group'])
            ->whereHas('group', function ($q) {
                $q->where('name', 'Meat Order');
            })
            ->whereRaw("UPPER(receipt_name) IN ('" . implode("','", $allCodes) . "')")
            ->where('is_modifier_only', true)
            ->where('is_available', true)
            ->get()
            ->keyBy(function ($m) {
                return strtoupper($m->receipt_name ?? '');
            });

        // Key menuRows by receipt_name for quick lookup. Normalize key lookup
        // to uppercase so matching is case-insensitive.
        $rowsByReceipt = collect();
        foreach ($menuRows as $row) {
            $receipt = data_get($row, 'receipt_name') ?? (is_object($row) ? ($row->receipt_name ?? null) : null);
            if ($receipt) {
                $rowsByReceipt->put(strtoupper($receipt), $row);
            }
        }

        $result = collect();

        foreach ($codes as $pkgId => $codeList) {
            $pkgModel = $packages->get($pkgId);

            // Use MenuResource shape for package summary when possible so the
            // structure matches API responses elsewhere.
            $pkgArr = $pkgModel ? \App\Http\Resources\MenuResource::make($pkgModel)->resolve() : ['id' => $pkgId];
            $pkgArr['modifiers'] = [];

            foreach ($codeList as $code) {
                $codeUpper = strtoupper($code);

                // Primary: use the pre-fetched modifier models (this collection
                // contains the correct modifiers for packages).
                if ($modifierModels->has($codeUpper)) {
                    $pkgArr['modifiers'][] = MenuModifierResource::make($modifierModels->get($codeUpper))->resolve();
                    continue;
                }

                // Fallback: if stored-proc provided a raw row for this code,
                // include that row as an array so the package still lists the
                // modifier even if the local model is missing.
                if ($rowsByReceipt->has($codeUpper)) {
                    $row = $rowsByReceipt->get($codeUpper);
                    $pkgArr['modifiers'][] = is_object($row) ? (array) $row : $row;
                    continue;
                }

                // If neither exists, skip this code quietly.
            }

            $result->push($pkgArr);
        }

        return $result;
    }

    public function loadModifiers() {
    
        $modifiers = $this->getComputedModifiersAttribute();

        if( $modifiers->isEmpty() ) {
            $modifiers = $this->modifiers;
        }

        return $modifiers ?? [];
    }
}
