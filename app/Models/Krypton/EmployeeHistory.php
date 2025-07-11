<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class EmployeeHistory extends Model
{
    protected $connection = 'pos';
    protected $table = 'employee_histories';
    protected $primaryKey = 'id';
    
    public $timestamps = false;

}
