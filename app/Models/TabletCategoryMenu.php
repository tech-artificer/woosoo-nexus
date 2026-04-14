<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabletCategoryMenu extends Model
{
    protected $table = 'tablet_category_menu';

    public $timestamps = true;

    protected $fillable = [
        'tablet_category_id',
        'krypton_menu_id',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TabletCategory::class, 'tablet_category_id');
    }
}
