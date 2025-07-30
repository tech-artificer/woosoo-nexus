<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Krypton\Menu;

class MenuImage extends Model
{
    protected $table = 'menu_images';
    protected $primaryKey = 'id';

    protected $fillable = ['path', 'menu_id'];

    protected $casts = [
        'id' => 'integer',
        'menu_id' => 'integer',
    ];

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
