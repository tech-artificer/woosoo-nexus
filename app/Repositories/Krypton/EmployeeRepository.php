<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;

class EmployeeRepository
{
    protected $connection = 'pos';

    public function getActiveEmployees ()
    {
        try {
            return DB::connection($this->connection)->select('CALL get_active_employees()');

        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    public function getActiveEmployeesWithStatus ()
    {
        try {
            return DB::connection($this->connection)->select('CALL get_active_employees_with_status()');

        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }



    public function getEmployeeLogsForSession ($sessionId)
    {
        try {
            return DB::connection($this->connection)->select('CALL get_employee_logs_for_session(?)', $sessionId);

        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

     public function getLatestEmployeeLogs ($employeeId, $sessionId, $timeout = null)
    {
        try {
            return DB::connection($this->connection)->select('CALL get_latest_employee_logs(?, ?, ?)', [$sessionId , $employeeId, $timeout]);
            
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    
    
}