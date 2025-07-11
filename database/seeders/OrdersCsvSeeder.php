<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Throwable; 

class OrdersCsvSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = database_path('data/orders.csv'); // Adjust path as needed

        if (!file_exists($filePath)) {
            $this->command->error("Orders CSV file not found: {$filePath}");
            return;
        }

        $file = fopen($filePath, 'r');
        if (!$file) {
            $this->command->error("Could not open Orders CSV file: {$filePath}");
            return;
        }

        $header = fgetcsv($file); // Read header row
        $importedCount = 0;
        $failedCount = 0;

        $orderIdMap = []; // To store old_order_id -> new_order_id mapping

        DB::beginTransaction();
        try {
            // Skip header row
            $isHeader = true;
            while (($row = fgetcsv($file)) !== false) {
                if ($isHeader) {
                    $isHeader = false;
                    continue;
                }

                if (count($header) !== count($row)) {
                    $this->command->warn("Skipping row due to column mismatch: " . implode(',', $row));
                    $failedCount++;
                    continue;
                }
                $data = array_combine($header, $row);

                $oldOrderId = $data['old_order_id'] ?? null; // Ensure this column exists in your CSV

                // **Map CSV columns to Stored Procedure Parameters:**
                // (Copy the parameter mapping logic from the previous Artisan Command example)
                $pSessionId = (int) $data['session_id'];
                $pTerminalSessionId = (int) $data['terminal_session_id'];
                $pDateTimeOpened = Carbon::parse($data['date_time_opened']);
                $pDateTimeClosed = !empty($data['date_time_closed']) ? Carbon::parse($data['date_time_closed']) : null;
                $pRevenueId = (int) $data['revenue_id'];
                $pTerminalId = (int) $data['terminal_id'];
                $pCustomerId = !empty($data['customer_id']) ? (int) $data['customer_id'] : null;
                $pIsOpened = filter_var($data['is_open'], FILTER_VALIDATE_BOOLEAN);
                $pIsTransferred = filter_var($data['is_transferred'], FILTER_VALIDATE_BOOLEAN);
                $pIsVoided = filter_var($data['is_voided'], FILTER_VALIDATE_BOOLEAN);
                $pGuestCount = (int) $data['guest_count'];
                $pServiceTypeId = (int) $data['service_type_id'];
                $pStartEmployeeLogId = (int) $data['start_employee_log_id'];
                $pCurrentEmployeeLogId = (int) $data['current_employee_log_id'];
                $pCloseEmployeeLogId = !empty($data['close_employee_log_id']) ? (int) $data['close_employee_log_id'] : null;
                $pServerEmployeeLogId = !empty($data['server_employee_log_id']) ? (int) $data['server_employee_log_id'] : null;
                $pReference = '';
                $pCashierEmployeeId = !empty($data['cashier_employee_id']) ? (int) $data['cashier_employee_id'] : null;
                $pTerminalServiceId = (int) $data['terminal_service_id'];
                $pIsOnlineOrder = filter_var($data['is_online_order'], FILTER_VALIDATE_BOOLEAN);

                $params = [
                    $pSessionId, $pTerminalSessionId, $pDateTimeOpened, $pDateTimeClosed,
                    $pRevenueId, $pTerminalId, $pCustomerId, $pIsOpened, $pIsTransferred,
                    $pIsVoided, $pGuestCount, $pServiceTypeId, $pStartEmployeeLogId,
                    $pCurrentEmployeeLogId, $pCloseEmployeeLogId, $pServerEmployeeLogId,
                    $pReference, $pCashierEmployeeId, $pTerminalServiceId, $pIsOnlineOrder
                ];

                $orderResult = DB::connection('pos')->select('CALL create_order(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $params);

                if (!empty($orderResult)) {
                    $newOrderId = $orderResult[0]->id;
                    if ($oldOrderId !== null) {
                        $orderIdMap[$oldOrderId] = $newOrderId;
                    }
                    $importedCount++;
                } else {
                    $this->command->warn("Failed to insert row: " . implode(',', $row));
                    $failedCount++;
                }
            }

            DB::commit();
            $this->command->info("Imported {$importedCount} orders. Failed: {$failedCount}.");
            // Store the map for other seeders to use if run separately
            file_put_contents(storage_path('app/order_id_map.json'), json_encode($orderIdMap));

        } catch (Throwable $e) { // Use Throwable to catch all errors and exceptions
            DB::rollBack();
            $this->command->error("Error during import: " . $e->getMessage());
            // Optionally re-throw if you want the seeding process to fail completely
            throw $e;
        } finally {
            fclose($file);
        }
    }
}
