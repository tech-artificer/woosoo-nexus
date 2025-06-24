<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Repositories\Krypton\EmployeeRepository;

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

    public function position() : BelongsTo
    {
        return $this->belongsTo(EmployeePosition::class);
    }

    public static function getActiveEmployees() {

        $employeeRepository = new EmployeeRepository();

        return $employeeRepository->getActiveEmployees();
        // $activeEmployees = $employeeRepository->getActiveEmployees();

        // $cashier = null;
        // foreach ($activeEmployees as $employee) {

        //     $employeePostionId = $employee->employee_position_id;

        //     $position = EmployeePosition::where('id', $employeePostionId)->first()->pluck('name');

        //     if( $position == 'Cashier') {
        //         $cashier = $employee;
        //     }
        // }

        // return $cashier;

    }

}
