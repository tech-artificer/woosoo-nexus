<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;

use App\Models\Krypton\Table;

class TableRepository
{

    public function getActiveTableOrders()
    {
        try {
            return Table::fromQuery('CALL get_active_table_orders()');
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    public function getActiveTableOrdersByTableGroup($tableGroupId)
    {
        try {
            return Table::fromQuery($this->connection)->select('CALL get_active_table_orders_by_table_group(?)', $tableGroupId);
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }


    public static function getActiveTableOrderByTable($tableId)
    {   
        try {
            return Table::fromQuery('CALL get_active_table_order_by_table(?)', [$tableId])->first();
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    

    
}