<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

/**
 * Tax model for Krypton POS database (legacy system)
 * 
 * Read-only integration with Krypton POS `krypton_woosoo` database.
 * POS tables do not include created_at/updated_at timestamps,
 * so timestamps are disabled for all Krypton models.
 */
class Tax extends Model
{
    protected $connection = 'pos';
    protected $table = 'taxes';
    protected $primaryKey = 'id';
    public $timestamps = false; // POS DB tables have no timestamp columns
}
