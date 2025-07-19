<?php

namespace App\Actions\Order;

use Lorisleiva\Actions\Concerns\AsAction;

use App\Models\Krypton\TableOrder;
use App\Models\Krypton\Order;
use App\Models\Krypton\Table;
use App\Models\Device;

class CreateTableOrder
{
    use AsAction;

    public function handle(array $attr)
    {
        return $this->createTableOrder($attr);
    }

    protected function createTableOrder(array $attr = []) 
    {
        try {
            $params = [
                $attr['order_id'], // Order ID
                $attr['table_id'],
                $attr['parent_table_id'] ?? null // Parent Table ID, can be null
            ];

            $placeholdersArray = array_fill(0, count($params), '?');
            $placeholders = implode(', ', $placeholdersArray);

            TableOrder::fromQuery('CALL create_table_order(' . $placeholders . ')', $params)->first();

            $tableOrder = TableOrder::where('order_id', $attr['order_id'])
                ->where('table_id', $attr['table_id'])
                ->first();

            return $tableOrder;

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }
}
