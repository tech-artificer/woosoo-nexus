<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

/**
 * ModifierGroup model for Krypton POS database (legacy system)
 * 
 * Read-only integration with Krypton POS `krypton_woosoo` database.
 * POS tables do not include created_at/updated_at timestamps.
 */
class ModifierGroup extends Model
{
    protected $connection = 'pos';
    protected $table = 'modifier_groups';
    public $timestamps = false; // POS DB tables have no timestamp columns
}
