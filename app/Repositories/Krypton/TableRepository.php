<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Krypton\Table;

class TableRepository
{

    public function getActiveTableOrders()
    {
        try {
            if (app()->environment('testing') || env('APP_ENV') === 'testing') {
                return Table::all();
            }

            return Table::fromQuery('CALL get_active_table_orders()');
        } catch (\Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    public function getActiveTableOrdersByTableGroup($tableGroupId)
    {
        try {
            if (app()->environment('testing') || env('APP_ENV') === 'testing') {
                return collect([]);
            }

            return Table::fromQuery('CALL get_active_table_orders_by_table_group(?)', [$tableGroupId]);
        } catch (\Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }


    public static function getActiveTableOrderByTable($tableId)
    {   
        try {
            if (app()->environment('testing') || env('APP_ENV') === 'testing') {
                return Table::where('id', $tableId)->first();
            }

            return Table::fromQuery('CALL get_active_table_order_by_table(?)', [$tableId])->first();
        } catch (\Exception $e) {
            Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    

    
}