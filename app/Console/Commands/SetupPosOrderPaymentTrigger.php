<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetupPosOrderPaymentTrigger extends Command
{
    private const OrderOutboxTable = 'woosoo_order_status_outbox';

    private const SessionOutboxTable = 'woosoo_session_status_outbox';

    protected $signature = 'pos:setup-payment-trigger';

    protected $description = 'Creates a POS-local payment status outbox and trigger for Nexus reconciliation.';

    public function handle(): int
    {
        $connection = DB::connection('pos');
        $this->createOrderOutboxTable();
        $this->createSessionOutboxTable();

        $connection->unprepared('DROP TRIGGER IF EXISTS after_payment_update');
        $connection->unprepared('DROP TRIGGER IF EXISTS after_session_close_update');

        if ($connection->getDriverName() !== 'mysql') {
            $this->info('POS outbox tables created. Trigger creation skipped for non-MySQL POS connection.');

            return self::SUCCESS;
        }

        $orderTriggerSql = <<<'SQL'
        CREATE TRIGGER after_payment_update
        AFTER UPDATE ON `orders`
        FOR EACH ROW
        BEGIN
          -- Act when order is closed/voided or when it transitions from open to closed
          IF (NEW.date_time_closed IS NOT NULL
              OR NEW.is_voided = 1
              OR (OLD.is_open = 1 AND NEW.is_open = 0)) THEN

            INSERT INTO `woosoo_order_status_outbox` (
                `pos_order_id`,
                `target_status`,
                `is_voided`,
                `is_open`,
                `date_time_closed`,
                `created_at`,
                `updated_at`
            ) VALUES (
                CAST(NEW.id AS UNSIGNED),
                CASE
                WHEN NEW.is_voided = 1 THEN 'voided'
                ELSE 'completed'
                END,
                NEW.is_voided,
                NEW.is_open,
                NEW.date_time_closed,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                `target_status` = IF(`processed_at` IS NULL, VALUES(`target_status`), `target_status`),
                `is_voided` = VALUES(`is_voided`),
                `is_open` = VALUES(`is_open`),
                `date_time_closed` = VALUES(`date_time_closed`),
                `last_error` = IF(`processed_at` IS NULL, NULL, `last_error`),
                `failed_at` = IF(`processed_at` IS NULL, NULL, `failed_at`),
                `updated_at` = NOW();

          END IF;
        END;
        SQL;

        $sessionTriggerSql = <<<'SQL'
        CREATE TRIGGER after_session_close_update
        AFTER UPDATE ON `sessions`
        FOR EACH ROW
        BEGIN
          -- Daily POS cashier-session close is restaurant-wide; keep it separate from per-order completion.
          IF (OLD.date_time_closed IS NULL AND NEW.date_time_closed IS NOT NULL) THEN

            INSERT INTO `woosoo_session_status_outbox` (
                `pos_session_id`,
                `event_type`,
                `date_time_closed`,
                `created_at`,
                `updated_at`
            ) VALUES (
                CAST(NEW.id AS UNSIGNED),
                'closed',
                NEW.date_time_closed,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                `event_type` = IF(`processed_at` IS NULL, VALUES(`event_type`), `event_type`),
                `date_time_closed` = VALUES(`date_time_closed`),
                `last_error` = IF(`processed_at` IS NULL, NULL, `last_error`),
                `failed_at` = IF(`processed_at` IS NULL, NULL, `failed_at`),
                `updated_at` = NOW();

          END IF;
        END;
        SQL;

        $connection->unprepared($orderTriggerSql);
        $connection->unprepared($sessionTriggerSql);

        $this->info('POS order payment and session-close outbox tables/triggers created successfully.');
        $this->info('Nexus will consume the POS-local outbox via php artisan pos:consume-payment-status-events.');

        return self::SUCCESS;
    }

    private function createOrderOutboxTable(): void
    {
        $schema = Schema::connection('pos');

        if ($schema->hasTable(self::OrderOutboxTable)) {
            return;
        }

        $schema->create(self::OrderOutboxTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pos_order_id');
            $table->string('target_status', 32);
            $table->boolean('is_voided')->nullable();
            $table->boolean('is_open')->nullable();
            $table->dateTime('date_time_closed')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('pos_order_id', 'woosoo_outbox_pos_order_unique');
            $table->index(['processed_at', 'failed_at', 'attempts'], 'woosoo_outbox_consume_idx');
        });
    }

    private function createSessionOutboxTable(): void
    {
        $schema = Schema::connection('pos');

        if ($schema->hasTable(self::SessionOutboxTable)) {
            return;
        }

        $schema->create(self::SessionOutboxTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pos_session_id');
            $table->string('event_type', 32);
            $table->dateTime('date_time_closed')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('pos_session_id', 'woosoo_outbox_pos_session_unique');
            $table->index(['processed_at', 'failed_at', 'attempts'], 'woosoo_session_outbox_consume_idx');
        });
    }
}
