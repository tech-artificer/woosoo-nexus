<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "session_id" => $this->session_id,
            "terminal_session_id" => $this->terminal_session_id,
            "date_time_opened" => $this->date_time_opened,
            "date_time_closed" => $this->date_time_closed,
            "revenue_id" => $this->revenue_id,
            "terminal_id" => $this->terminal_id,
            "current_terminal_id" => $this->current_terminal_id,
            "end_terminal_id" => $this->end_terminal_id,
            "customer_id" => $this->customer_id,
            "is_open" => $this->is_open,
            "is_transferred" => $this->is_transferred,
            "is_voided" => $this->is_voided,
            "guest_count" => $this->guest_count,
            "service_type_id" => $this->service_type_id,
            "is_available" => $this->is_available,
            "cash_tray_session_id" => $this->cash_tray_session_id,
            "server_banking_session_id" => $this->server_banking_session_id,
            "start_employee_log_id" => $this->start_employee_log_id,
            "current_employee_log_id" => $this->current_employee_log_id,
            "close_employee_log_id" => $this->close_employee_log_id,
            "server_employee_log_id" => $this->server_employee_log_id,
            "transaction_no" => $this->transaction_no,
            "reference" => $this->reference,
            "created_on" => $this->created_on,
            "modified_on" => $this->modified_on,
            "cashier_employee_id" => $this->cashier_employee_id,
            "terminal_service_id" => $this->terminal_service_id,
            "is_online_order" => $this->is_online_order,
            "reprint_count" => $this->reprint_count,
            "table" => $this->table
        ];
    }
}
