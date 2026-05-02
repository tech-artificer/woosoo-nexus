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
    public function handle(): int
    {
        // POS DB connection (trigger will be created here)
        $connection = DB::connection('pos');

        // MySQL trigger body can only access tables on the same MySQL server instance.
        // When app DB and POS DB are on different hosts/ports (e.g., Docker + external POS),
        // cross-server table updates are impossible from a trigger.
        if (! $this->isSameMysqlEndpoint()) {
            $connection->unprepared('DROP TRIGGER IF EXISTS after_payment_update');

            $this->error('Cannot create after_payment_update trigger: POS and app databases are on different MySQL endpoints.');
            $this->line('MySQL triggers cannot update tables on another server/port.');
            $this->newLine();
            $this->line('POS endpoint: ' . $this->describeConnectionEndpoint('pos'));
            $this->line('APP endpoint: ' . $this->describeConnectionEndpoint('mysql'));
            $this->newLine();
            $this->line('Action required: use an application-level sync flow for payment status reconciliation.');
            $this->line('This project now provides: php artisan pos:sync-payment-statuses');

            return self::FAILURE;
        }

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

        return self::SUCCESS;
    }

    private function isSameMysqlEndpoint(): bool
    {
        $pos = config('database.connections.pos', []);
        $app = config('database.connections.mysql', []);

        $keys = ['host', 'port', 'unix_socket'];

        foreach ($keys as $key) {
            $left = (string) ($pos[$key] ?? '');
            $right = (string) ($app[$key] ?? '');

            if ($left !== $right) {
                return false;
            }
        }

        return true;
    }

    private function describeConnectionEndpoint(string $connection): string
    {
        $config = config("database.connections.{$connection}", []);
        $host = (string) ($config['host'] ?? '');
        $port = (string) ($config['port'] ?? '');
        $socket = (string) ($config['unix_socket'] ?? '');

        if ($socket !== '') {
            return sprintf('%s (socket: %s)', $connection, $socket);
        }

        return sprintf('%s (%s:%s)', $connection, $host !== '' ? $host : 'unknown-host', $port !== '' ? $port : 'unknown-port');
    }
}
