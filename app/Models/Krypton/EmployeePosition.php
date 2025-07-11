<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeePosition extends Model
{
    protected $connection = 'pos';
    protected $table = 'employee_positions';
    protected $primaryKey = 'id';

    public function employee() : HasOne
    {
        return $this->hasOne(Employee::class, 'id', 'employee_id');
    }

}
