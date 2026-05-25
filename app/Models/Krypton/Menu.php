<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\MenuImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
    protected bool $uploadedImagesAttached = false;

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

        return number_format((float) $taxAmount, $decimals, '.', ',');
    }

    /**
     * Image resolution chain, in order:
     *   1. menu_images.path  (admin-uploaded via the media library;
     *      stored under storage/app/public/, served via /storage/ symlink)
     *   2. Bundled brand asset matched against name/kitchen_name/receipt_name
     *      slug (lives in public/images/food-assets/, served on :443 and :4443
     *      via the nginx /images/ location block added 2026-05-21).
     *   3. Generic placeholder (public/images/menu-placeholder/2.webp).
     *
     * Future improvement: per-category placeholders (pork/beef/banchan/etc.)
     * — requires design assets first; for now a single placeholder is the
     * defensible default and the brand-asset map covers the active menu.
     */
    public function getImageUrlAttribute()
    {
        $loadedImage = $this->relationLoaded('image') ? $this->getRelation('image') : null;
        $imgPath = $loadedImage?->path;

        // MenuImage lives on the default (mysql) connection; Menu lives on the pos connection.
        // Eager-loading `image` across connections can bind an empty relation, so one-off
        // callers still need the direct fallback. Bulk callers mark the lookup as completed
        // through `attachUploadedImages()` so missing images do not cause N+1 queries.
        if ($imgPath === null && ! $this->uploadedImagesAttached) {
            $imgPath = MenuImage::where('menu_id', $this->id)->value('path');
        }

        if ($imgPath) {
            return url('storage/' . $imgPath);
        }

        $brandImageFile = $this->resolveBrandFoodAssetFile();
        if ($brandImageFile) {
            return asset('images/food-assets/' . $brandImageFile);
        }

        // 2.webp is the newer/lighter placeholder; 1.jpg is the legacy one.
        return asset('images/menu-placeholder/2.webp');
    }

    /**
     * Bulk-attach uploaded MenuImage records to a Menu or collection of Menus.
     *
     * Works around the cross-connection eager-load gap (Menu on `pos`, MenuImage on
     * `mysql`). Call this immediately after fetching Menu models that will be
     * serialized through `MenuResource` / `MenuModifierResource`, so each
     * `image_url` accessor sees a populated relation and avoids per-row queries.
     *
     * Accepts a single Menu, an Eloquent/Support Collection of Menus, an array of
     * Menus, or null. Silently no-ops on empty input. Models that are not Menu
     * instances are skipped.
     *
     * @param  Menu|iterable<Menu>|null  $menus
     */
    public static function attachUploadedImages($menus): void
    {
        if ($menus === null) {
            return;
        }

        if ($menus instanceof self) {
            $menus = [$menus];
        }

        $collection = collect($menus);
        if ($collection->isEmpty()) {
            return;
        }

        $ids = $collection
            ->filter(fn ($m) => $m instanceof self)
            ->pluck('id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return;
        }

        $images = MenuImage::whereIn('menu_id', $ids)->get()->keyBy('menu_id');

        foreach ($collection as $menu) {
            if ($menu instanceof self) {
                $menu->setRelation('image', $images->get($menu->id));
                $menu->uploadedImagesAttached = true;
            }
        }
    }

    protected function resolveBrandFoodAssetFile(): ?string
    {
        $map = [
            'asian-gochu-woosamgyup' => 'asian-gochu-woosamgyup.png',
            'beef-bulgogi' => 'beef-bulgogi.png',
            'candied-sweet-potato-goguma-mattang' => 'candied-sweet-potato-goguma-mattang.png',
            'citrus-burst-pepper-samgyupsal' => 'citrus-burst-pepper-samgyupsal.png',
            'dak-galbi' => 'dak-galbi-plain-or-spicy.png',
            'dak-galbi-plain-or-spicy' => 'dak-galbi-plain-or-spicy.png',
            'gamja-jorim-korean-braised-baby-potatoes' => 'gamja-jorim-korean-braised-baby-potatoes.png',
            'golden-mushroom-beef-roll' => 'golden-mushroom-beef-roll.png',
            'golden-mushroom-roll' => 'golden-mushroom-roll.png',
            'gyeran-jjim-egg-souffle' => 'gyeran-jjim-egg-souffle.png',
            'hyangcho-samgyupsal' => 'hyangcho-samgyupsal-1.png',
            'hyangcho-woosamgyup' => 'hyangcho-woosamgyup.png',
            'kajun-bulmat-samgyupsal' => 'kajun-bulmat-samgyupsal.png',
            'korean-chili-pepper-beef' => 'korean-chili-pepper-beef.png',
            'korean-chili-pepper-samgyupsal' => 'korean-chili-pepper-samgyupsal.png',
            'korean-lettuce-salad-sanghu-geotjeori' => 'korean-lettuce-salad-sanghu-geotjeori.png',
            'korean-pickled-radish' => 'korean-pickled-radish.png',
            'korean-potato-salad-gamja-salad' => 'korean-potato-salad-gamja-salad.png',
            'lettuce' => 'lettuce.png',
            'moksal' => 'moksal-pork-neck.png',
            'moksal-pork-neck' => 'moksal-pork-neck.png',
            'citrus-burst-woosamgyup' => 'asian-gochu-woosamgyup.png',
            'secret-spice-samgyupsal' => 'samgyupsal.png',
            'secret-spice-woosamgyup' => 'woosamgyup.png',
            'spicy-sesame-woosamgyup' => 'woosamgyup.png',
            'pickled-cucumber' => 'pickled-cucumber.png',
            'plain-samgyupsal' => 'plain-samgyupsal.png',
            'samgyupsal' => 'samgyupsal.png',
            'spicy-sesame-samgyupsal' => 'spicy-sesame-samgyupsal.png',
            'sweet-and-crunchy-tofu-dubu-ganjeong' => 'sweet-and-crunchy-tofu-dubu-ganjeong.png',
            'traditional-napa-cabbage-kimchi' => 'traditional-napa-cabbage-kimchi.png',
            'woosamgyup' => 'woosamgyup.png',
            'woosoo-cheese' => 'woosoo-cheese.png',
            'yangyeom-samgyupsal' => 'yangyeom-samgyupsal.png',
        ];

        $candidates = [
            $this->name,
            $this->kitchen_name,
            $this->receipt_name,
        ];

        foreach ($candidates as $candidate) {
            if (! $candidate || ! is_string($candidate)) {
                continue;
            }

            $slug = Str::of($candidate)
                ->lower()
                ->replace('&', 'and')
                ->replace(['(', ')', '/', ',', '.'], ' ')
                ->squish()
                ->slug('-')
                ->toString();

            if (isset($map[$slug])) {
                return $map[$slug];
            }
        }

        return null;
    }

    /**
     * Build a database-agnostic ORDER BY clause using CASE statement.
     * Works with SQLite (testing) and MySQL (production).
     * Preserves the defined code order while maintaining database portability.
     *
     * @param array $codeList The ordered list of codes (e.g., ['P1', 'P2', 'P3'])
     * @return string The CASE WHEN clause for orderByRaw()
     */

    public function getComputedModifiersAttribute()
    {
        if (!in_array($this->id, [46, 47, 48])) {
            return collect(); // Return empty collection if not a "package" menu
        }
        $codes = [
            46 => ['P1', 'P2', 'P3', 'P4', 'P5'],
            47 => ['P1', 'P2', 'P3', 'P4', 'P5', 'B1', 'B2', 'B3'],
            48 => [
                'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8', 'P9', 'P10',
                'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9',
                'C1', 'C2',
            ],
        ];

        if (! isset($codes[$this->id]) || empty($codes[$this->id])) {
            return collect();
        }

        $codeList = $codes[$this->id];

        // Return modifier menus matching the receipt codes for this package.
        // Do not restrict by group name so the set meal modifiers include the
        // same menu rows/fields as the regular modifiers endpoint. Preserve
        // the defined code order across both MySQL and SQLite.
        $query = Menu::with(['image', 'group', 'category'])
            ->whereIn('receipt_name', $codeList)
            ->where('is_modifier_only', true);

        return self::orderByReceiptCodeList($query, $codeList)->get();
    }

    /**
     * Apply deterministic ordering for a fixed receipt-code list.
     *
     * Uses a CASE expression for portability (SQLite test DB + MySQL runtime)
     * while preserving the exact incoming codeList order.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array<int, string> $codeList
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function orderByReceiptCodeList(Builder $query, array $codeList): Builder
    {
        if (empty($codeList)) {
            return $query;
        }

        $caseSegments = [];
        $bindings = [];

        foreach (array_values($codeList) as $index => $code) {
            $caseSegments[] = 'WHEN receipt_name = ? THEN '.$index;
            $bindings[] = $code;
        }

        $caseSql = 'CASE '.implode(' ', $caseSegments).' ELSE '.count($codeList).' END';

        return $query->orderByRaw($caseSql, $bindings);
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
                'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8', 'P9', 'P10',
                'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9',
                'C1', 'C2',
            ],
        ];

        // Fetch package menu models (if present locally)
        $packageIds = array_keys($codes);
        $packages = Menu::with(['image', 'group', 'category', 'course', 'tax'])->whereIn('id', $packageIds)->get()->keyBy('id');

        // Flatten all codes to prefetch modifier menu models as fallback and
        // normalize to uppercase to avoid case-mismatch between POS rows
        // and local DB values.
        $allCodes = array_values(array_unique(array_map('strtoupper', array_merge(...array_values($codes)))));

        // Restrict modifiers to the 'Meat Order' group (same as controller
        // `getMenuModifiers`) to ensure the package modifiers are selected
        // from the same set the /menus/modifiers endpoint returns. Key the
        // resulting collection by UPPERCASE receipt_name for reliable lookup.
        $modifierModels = Menu::with(['image', 'group', 'category'])
            ->whereHas('group', function ($q) {
                $q->where('name', 'Meat Order');
            })
            ->whereIn(DB::raw('UPPER(receipt_name)'), $allCodes)
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
