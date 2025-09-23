<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    protected $connection = 'pos';
    protected $table = 'service_types';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;
}
