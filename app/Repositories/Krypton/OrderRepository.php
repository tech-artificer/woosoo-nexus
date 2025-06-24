<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;

class OrderRepository
{
    protected $connection = 'pos';

    public function createOrderItem(Menu $menu, array $data)
    {
        $placeholder = [];
        $params = [];

        try {
            return DB::connection($this->connection)->select('CALL get_active_table_orders('. $placeholder .')', $params);
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Failed to fetch active table orders.');
        }
    }

    
    
}