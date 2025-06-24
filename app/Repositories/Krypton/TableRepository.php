<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;

class OrderRepository
{

    protected $connection = 'pos';

    public function getActiveTableOrders()
    {
        try {
            return DB::connection($this->connection)->select('CALL get_active_table_orders()');
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Failed to fetch active table orders.');
        }
    }

    public function getActiveTableOrdersByTableGroup($tableGroupId)
    {
        try {
            return DB::connection($this->connection)->select('CALL get_active_table_orders_by_table_group(?)');
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Failed to fetch active table orders.');
        }
    }

    public function getActiveTableOrderByTable($tableId)
    {
        try {
            return DB::connection($this->connection)->select('CALL get_active_table_order_by_table(?)');
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Failed to fetch active table orders.');
        }
    }

    

    
}