<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupPosOrderPaymentTrigger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:setup-payment-trigger';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a trigger on orders table in the POS database to update device_orders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // POS DB connection (trigger will be created here)
        $connection = DB::connection('pos');

        // App DB (where device_orders lives)
        $appDb = DB::connection('mysql')->getDatabaseName();
        $appDbEscaped = str_replace('`', '', $appDb);
        $fqDeviceOrdersTable = "`{$appDbEscaped}`.`device_orders`";

        // Drop any existing trigger and create a new one that updates device_orders directly
        $connection->unprepared("DROP TRIGGER IF EXISTS after_payment_update");

        $triggerSql = <<<SQL
        CREATE TRIGGER after_payment_update
        AFTER UPDATE ON `orders`
        FOR EACH ROW
        BEGIN
          -- Act when order is closed/voided or when it transitions from open to closed
          IF (NEW.date_time_closed IS NOT NULL
              OR NEW.is_voided = 1
              OR (OLD.is_open = 1 AND NEW.is_open = 0)) THEN

            -- Determine the new status for device_orders
            SET @new_status = CASE 
                WHEN NEW.is_voided = 1 THEN 'voided'
                ELSE 'completed'
            END;

            -- Directly update device_orders table (cross-database)
            UPDATE {$fqDeviceOrdersTable}
            SET 
                status = @new_status,
                updated_at = NOW()
            WHERE order_id = NEW.id;

          END IF;
        END;
        SQL;

        $connection->unprepared($triggerSql);

        $this->info('Trigger "after_payment_update" created successfully.');
        $this->info('The trigger will now directly update device_orders status when orders are paid/voided.');

    }
}
