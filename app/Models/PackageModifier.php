<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageModifier extends Model
{
    protected $fillable = [
        'package_id',
        'krypton_menu_id',
        'sort_order',
    ];

    protected $casts = [
        'package_id'      => 'integer',
        'krypton_menu_id' => 'integer',
        'sort_order'      => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
