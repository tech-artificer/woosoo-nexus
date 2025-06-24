<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuImage extends Model
{
    protected static function booted()
    {
        static::deleting(function ($image) {
            Storage::disk('public')->delete($image->path);
        });
    }
}
