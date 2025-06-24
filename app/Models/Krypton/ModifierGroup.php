<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class ModifierGroup extends Model
{
    protected $connection = 'pos';
    protected $table = 'modifier_groups';
}
