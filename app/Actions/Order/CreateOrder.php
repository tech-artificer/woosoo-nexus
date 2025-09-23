<?php

namespace App\Actions\Order;

use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Http\Request;
use App\Models\Krypton\Order;

class CreateOrder
{
    use AsAction;

    // public function handle(Device $device, array $params)
    public function handle(array $attr)
    {
       $order = $this->createNewOrder($attr);

        // Update the order with terminal and cashier details
        $order->update([
            'end_terminal_id' => $order->terminal_id, 
            'cash_tray_session_id' => $attr['cash_tray_session_id'],
            'cashier_employee_id' => $attr['cashier_employee_id'],
        ]);

        return $order->refresh();
    }

    public function createNewOrder(array $attr = []) {
        
        try {
            $sessionId = $attr['session_id'];
            $terminalSessionId = $attr['terminal_session_id'];
            $dateTimeOpened = now(); // Current date and time
            $dateTimeClosed = null; // Order is open initially
            $revenueId = $attr['revenue_id'] ?? 1; // Default revenue center
            $terminalId = $attr['terminal_id'] ?? 1; // Current POS terminal ID
            $customerId = $attr['customer_id'] ?? null; // Can be null
            $isOpen = true; // Default to true, can be set based on request
            $isTransferred = false; // Default to false, can be set based on request
            $isVoided = false; // Default to false, can be set based on request
            $guestCount = $attr['guest_count'] ?? 1;
            $serviceTypeId = $attr['service_type_id'] ?? 1; // e.g., 1 for Dine-In
            $startEmployeeLogId = $attr['start_employee_log_id']; // $attr['start_employee_log_id']; // From logged-in user or default
            $currentEmployeeLogId = $attr['current_employee_log_id']; //$attr['current_employee_log_id'];
            $closeEmployeeLogId = $attr['close_employee_log_id']; // //$attr['close_employee_log_id'] ?? null;
            $serverEmployeeLogId = $attr['server_employee_log_id']; //$attr['server_employee_log_id'] ?? $startEmployeeLogId;
            $reference = $attr['reference'] ?? null; // Can be null
            $cashierEmployeeId = $attr['cashier_employee_id'] ?? 2;
            $terminalServiceId = $attr['terminal_service_id'];
            $isOnlineOrder = $attr['is_online_order']; // Default to false, can be set based on request

            $params = [
                $sessionId, 
                $terminalSessionId, 
                $dateTimeOpened, 
                $dateTimeClosed, 
                $revenueId, 
                $terminalId,
                $customerId, 
                $isOpen, 
                $isTransferred,
                $isVoided, 
                $guestCount, 
                $serviceTypeId,
                $startEmployeeLogId,
                $currentEmployeeLogId, 
                $closeEmployeeLogId, 
                $serverEmployeeLogId,
                $reference, 
                $cashierEmployeeId, 
                $terminalServiceId, 
                $isOnlineOrder
            ];

    
            $placeholdersArray = array_fill(0, count($params), '?');
            $placeholders = implode(', ', $placeholdersArray);
            // Call the procedure
            $order = Order::fromQuery('CALL create_order(' . $placeholders . ')', $params)->first();

            if (empty($order)) {
                throw new \Exception("Failed to create new order.");
            }
            // Return success or proceed to next steps
            return $order;

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }
}
