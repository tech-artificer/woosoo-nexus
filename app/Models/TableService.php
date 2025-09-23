<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableService extends Model
{
    protected $table = 'table_services';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    

}
