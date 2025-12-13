<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Actions\Pos\FillOrderPaymentColumns;

class PosFillOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:fill-order
                            {orderId : The POS order id}
                            {--date_time_closed= : Date/time to set (Y-m-d H:i:s)}
                            {--is_open= : 0 or 1}
                            {--is_voided= : 0 or 1}
                            {--session_id= : session id integer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update POS order payment fields for testing triggers';

    public function handle()
    {
        $orderId = (int) $this->argument('orderId');

        $attrs = [];
        if ($this->option('date_time_closed') !== null) {
            $attrs['date_time_closed'] = $this->option('date_time_closed');
        }
        if ($this->option('is_open') !== null) {
            $attrs['is_open'] = (int) $this->option('is_open');
        }
        if ($this->option('is_voided') !== null) {
            $attrs['is_voided'] = (int) $this->option('is_voided');
        }
        if ($this->option('session_id') !== null) {
            $attrs['session_id'] = (int) $this->option('session_id');
        }

        try {
            $updated = FillOrderPaymentColumns::run($orderId, $attrs);
            $this->info('POS order updated:');
            $this->line(json_encode($updated));
            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            return 1;
        }
    }
}
