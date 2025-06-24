<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableService extends Model
{
    protected $table = 'table_services';

    protected $fillable = [
        'name'
    ];

}
