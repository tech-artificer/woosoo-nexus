<?php

namespace App\Testing\Fakes\Krypton;

use App\Repositories\Krypton\EmployeeRepository;
use Illuminate\Support\Collection;

class FakeEmployeeRepository extends EmployeeRepository
{
    public function getActiveEmployees(): Collection
    {
        return collect([]);
    }

    public function getActiveEmployeesWithStatus(): Collection
    {
        return collect([]);
    }

    public function getEmployeeLogsForSession($sessionId)
    {
        return [];
    }

    public function getLatestEmployeeLogs($employeeId, $sessionId, $timeout = null)
    {
        return [];
    }
}
