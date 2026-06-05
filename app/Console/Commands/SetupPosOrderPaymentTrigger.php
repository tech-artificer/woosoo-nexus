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

    private const DetailOutboxTable = 'woosoo_order_detail_outbox';

    protected $signature = 'pos:setup-payment-trigger';

    protected $description = 'Creates a POS-local payment status outbox and trigger for Nexus reconciliation.';

    public function handle(): int
    {
        $connection = DB::connection('pos');
        $this->createOrderOutboxTable();
        $this->createSessionOutboxTable();
        $this->createDetailOutboxTable();

        $connection->unprepared('DROP TRIGGER IF EXISTS after_payment_update');
        $connection->unprepared('DROP TRIGGER IF EXISTS after_session_close_update');
        $connection->unprepared('DROP TRIGGER IF EXISTS after_order_detail_update');

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

        // NEX-CASE-013: POS-side detail-change triggers write to the detail outbox
        // when meaningful columns mutate. The narrow conditions prevent outbox
        // churn from no-op row touches; the consumer re-reads the local
        // DeviceOrder so we never trust POS column copies in the broadcast.
        //
        // - `orders` trigger fires on guest_count change (only verified detail
        //   column on the POS `orders` schema; totals live on `order_checks`).
        // - `order_checks` trigger fires on totals change; outbox row is keyed
        //   on the parent `order_checks.order_id`, so multiple check edits on
        //   one order collapse into a single re-queue.
        //
        // ON DUPLICATE KEY: if the existing row is already processed, reset
        // it to be re-queued (the detail signal is a new event); if still
        // in-flight (processed_at IS NULL), leave attempts/last_error alone.
        $detailOrdersTriggerSql = <<<'SQL'
        CREATE TRIGGER after_order_detail_update
        AFTER UPDATE ON `orders`
        FOR EACH ROW
        BEGIN
          IF (NOT (NEW.guest_count <=> OLD.guest_count)) THEN

            INSERT INTO `woosoo_order_detail_outbox` (
                `pos_order_id`,
                `created_at`,
                `updated_at`
            ) VALUES (
                CAST(NEW.id AS UNSIGNED),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                `processed_at` = IF(`processed_at` IS NULL, `processed_at`, NULL),
                `failed_at` = IF(`processed_at` IS NULL, `failed_at`, NULL),
                `last_error` = IF(`processed_at` IS NULL, `last_error`, NULL),
                `attempts` = IF(`processed_at` IS NULL, `attempts`, 0),
                `updated_at` = NOW();

          END IF;
        END;
        SQL;

        $detailOrderChecksTriggerSql = <<<'SQL'
        CREATE TRIGGER after_order_check_detail_update
        AFTER UPDATE ON `order_checks`
        FOR EACH ROW
        BEGIN
          IF (NOT (NEW.total_amount <=> OLD.total_amount)
              OR NOT (NEW.tax_amount <=> OLD.tax_amount)
              OR NOT (NEW.discount_amount <=> OLD.discount_amount)
              OR NOT (NEW.subtotal_amount <=> OLD.subtotal_amount)) THEN

            INSERT INTO `woosoo_order_detail_outbox` (
                `pos_order_id`,
                `created_at`,
                `updated_at`
            ) VALUES (
                CAST(NEW.order_id AS UNSIGNED),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                `processed_at` = IF(`processed_at` IS NULL, `processed_at`, NULL),
                `failed_at` = IF(`processed_at` IS NULL, `failed_at`, NULL),
                `last_error` = IF(`processed_at` IS NULL, `last_error`, NULL),
                `attempts` = IF(`processed_at` IS NULL, `attempts`, 0),
                `updated_at` = NOW();

          END IF;
        END;
        SQL;

        $connection->unprepared('DROP TRIGGER IF EXISTS after_order_check_detail_update');
        $connection->unprepared($orderTriggerSql);
        $connection->unprepared($sessionTriggerSql);
        $connection->unprepared($detailOrdersTriggerSql);
        $connection->unprepared($detailOrderChecksTriggerSql);

        $this->info('POS order payment, session-close, and detail-update outbox tables/triggers created successfully.');
        $this->info('Nexus will consume the POS-local outbox via php artisan pos:consume-payment-status-events and pos:consume-order-detail-events.');

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

    /**
     * NEX-CASE-013: POS-local outbox for order-detail changes (guest_count,
     * totals via order_checks). Schema mirrors the payment outbox minus the
     * status columns — the consumer re-reads the local DeviceOrder rather
     * than trusting denormalized POS values, so no detail columns are stored.
     */
    private function createDetailOutboxTable(): void
    {
        $schema = Schema::connection('pos');

        if ($schema->hasTable(self::DetailOutboxTable)) {
            return;
        }

        $schema->create(self::DetailOutboxTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pos_order_id');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('pos_order_id', 'woosoo_detail_outbox_pos_order_unique');
            $table->index(['processed_at', 'failed_at', 'attempts'], 'woosoo_detail_outbox_consume_idx');
        });
    }
}
