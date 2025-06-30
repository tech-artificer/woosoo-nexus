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




    protected $casts = [
        'id' => 'integer',  
    ];

    public function logs() : HasMany
    {
        return $this->hasMany(EmployeeLog::class, 'employee_id', 'id');
    }


   public function position(): HasOneThrough
    {
        return $this->hasOneThrough(
            EmployeePosition::class,
            EmployeeHistory::class,
            'employee_id',          // Foreign key on EmployeeHistory table (links to Employee)
            'id',                   // Primary key on EmployeePosition table (links to EmployeeHistory)
            'id',                   // Local key on Employee table (the 'id' of the employee)
            'employee_position_id'  // Foreign key on EmployeeHistory table (links to EmployeePosition)
        );
    }

    public static function getActiveEmployees() {

        $employeeRepository = new EmployeeRepository();

        return $employeeRepository->getActiveEmployees();
    }

}
