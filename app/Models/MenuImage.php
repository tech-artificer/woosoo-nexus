<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuImage extends Model
{
    protected static function booted()
    {
        static::deleting(function ($image) {
            Storage::disk('public')->delete($image->path);
        });
    }

    public function menu() : BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }
}
