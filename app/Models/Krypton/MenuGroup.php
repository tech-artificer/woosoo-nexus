<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuGroup extends Model
{
    protected $connection = 'pos';
    protected $table = 'menu_groups';
    protected $primaryKey = 'id';

    protected $casts = [
        'id' => 'integer',
        'menu_category_id' => 'integer',
        'menu_course_type_id' => 'integer'
    ];

}
