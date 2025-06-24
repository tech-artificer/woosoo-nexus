<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MenuCourse extends Model
{
    protected $connection = 'pos';
    protected $table = 'menu_course_types';
    protected $primaryKey = 'id';

    protected $casts = [
        'id' => 'integer',
    ];

    public function menus() : HasMany
    {
        return $this->hasMany(Menu::class);
    }
}
