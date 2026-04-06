<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'name',
        'krypton_menu_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'krypton_menu_id' => 'integer',
        'sort_order'      => 'integer',
    ];

    /**
     * Ordered modifiers for this package.
     * Each modifier references a Krypton menu record via krypton_menu_id.
     */
    public function modifiers(): HasMany
    {
        return $this->hasMany(PackageModifier::class)->orderBy('sort_order');
    }
}
