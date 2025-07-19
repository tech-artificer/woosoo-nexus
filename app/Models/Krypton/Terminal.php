<?php

namespace App\Models\Krypton;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\DB;

class Terminal extends Model
{
    protected $connection = 'pos';
    protected $table = 'terminals';
    protected $primaryKey = 'id';

    public $timestamps = false;

    public function employeeLogs()
    {
        return $this->hasMany(EmployeeLog::class, 'terminal_id');
    }
}
