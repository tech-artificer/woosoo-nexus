<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
    protected $description = 'Creates update_logs table and a trigger on order_check table in the POS database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = DB::connection('pos');
        
        if (!Schema::connection('mysql')->hasTable('order_update_logs')) {
            Schema::connection('mysql')->create('order_update_logs', function ($table) {
                $table->id();
                $table->string('table_name');
                $table->unsignedBigInteger('order_id')->unique();
                $table->boolean('is_open');
                $table->boolean('is_voided');
                $table->string('action');
                $table->boolean('is_processed')->default(false);
                $table->softDeletes();
                $table->timestamps();
            });

            $this->info('Created order_update_logs table in pos.');
        } else {

            if (!Schema::connection('mysql')->hasColumn('order_update_logs', 'date_time_closed')) {
                Schema::connection('mysql')->table('order_update_logs', function ($table) {
                    $table->dateTime('date_time_closed')->nullable();
                });
            }

            if (!Schema::connection('mysql')->hasColumn('order_update_logs', 'session_id')) {
                Schema::connection('mysql')->table('order_update_logs', function ($table) {
                    $table->integer('session_id')->nullable();
                });
            }

            $this->warn('order_update_logs table already exists.');
        }

        $connection->unprepared("DROP TRIGGER IF EXISTS after_payment_update");

        // Create new trigger
        $connection->unprepared("
            CREATE TRIGGER after_payment_update
            AFTER UPDATE ON orders
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM woosoo_api.order_update_logs WHERE order_id = NEW.id
                ) THEN
                    UPDATE woosoo_api.order_update_logs
                    SET 
                        is_open = NEW.is_open,
                        is_voided = NEW.is_voided,
                        action = CASE 
                                    WHEN NEW.is_voided = 1 THEN 'voided'
                                    ELSE 'paid'
                                END,
                        updated_at = NOW()
                    WHERE order_id = NEW.id;
                ELSE
                    IF NEW.date_time_closed IS NOT NULL THEN
                        INSERT INTO woosoo_api.order_update_logs 
                            (table_name, order_id, is_open, is_voided, action, created_at, updated_at, date_time_closed, session_id)
                        VALUES 
                            ('orders', NEW.id, NEW.is_open, NEW.is_voided, 
                            CASE 
                                WHEN NEW.is_voided = 1 THEN 'voided'
                                ELSE 'paid'
                            END, 
                            NOW(), NOW(), NEW.date_time_closed, NEW.session_id);
                    END IF;
                END IF;
            END
        ");

        $this->info('Trigger "after_payment_update" created successfully.');

    }
}
