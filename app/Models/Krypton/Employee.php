<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

use App\Repositories\Krypton\EmployeeRepository;
// use App\Models\Krypton\EmployeeHistory;

class Employee extends Model
{
    protected $connection = 'pos';
    protected $table = 'employees';
    protected $primaryKey = 'id';
    public $timestamps = false;

    
}
