<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

/**
 * Revenue model for Krypton POS database (legacy system)
 * 
 * Read-only integration with Krypton POS `krypton_woosoo` database.
 * POS tables do not include created_at/updated_at timestamps,
 * but may have custom date fields like 'date_time'.
 */
class Revenue extends Model
{
    protected $connection = 'pos';
    protected $table = 'revenues';
    protected $primaryKey = 'id';
    public $timestamps = false; // POS DB tables have no standard timestamp columns

    protected $dates = [
        'date_time',
    ];
}
