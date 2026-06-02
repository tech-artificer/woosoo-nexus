<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Events\Order\OrderDetailsUpdated;
use App\Models\DeviceOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * NEX-CASE-013: feature coverage for the POS detail-outbox consumer.
 *
 * Schema is created inline (mirroring PosPaymentOutboxConsumerTest) instead
 * of running the setup command — the SQLite POS test connection short-circuits
 * trigger creation in the command, but feature tests need the outbox table
 * to exist without the MySQL-only trigger SQL running.
 */
class PosOrderDetailSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = Schema::connection('pos');
        $schema->dropIfExists('woosoo_order_detail_outbox');
        $schema->create('woosoo_order_detail_outbox', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('pos_order_id');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function test_consumer_dispatches_order_details_updated_for_matched_pos_order_id(): void
    {
        Event::fake([OrderDetailsUpdated::class]);

        $order = DeviceOrder::factory()->confirmed()->create([
            'order_id' => 900301,
        ]);
        $outboxId = $this->insertDetailOutboxRow($order->order_id);

        $this->artisan('pos:consume-order-detail-events')->assertExitCode(0);

        $this->assertOutboxProcessed($outboxId);

        Event::assertDispatched(
            OrderDetailsUpdated::class,
            fn (OrderDetailsUpdated $event): bool => $event->order->order_id === $order->order_id
        );
    }

    public function test_consumer_is_idempotent_processed_rows_are_not_re_dispatched(): void
    {
        Event::fake([OrderDetailsUpdated::class]);

        $order = DeviceOrder::factory()->confirmed()->create([
            'order_id' => 900302,
        ]);
        $this->insertDetailOutboxRow($order->order_id);

        $this->artisan('pos:consume-order-detail-events')->assertExitCode(0);
        $this->artisan('pos:consume-order-detail-events')->assertExitCode(0);

        Event::assertDispatchedTimes(OrderDetailsUpdated::class, 1);
    }

    public function test_consumer_marks_row_failed_after_max_attempts_when_no_device_order_matches(): void
    {
        Event::fake([OrderDetailsUpdated::class]);

        // No DeviceOrder created for this pos_order_id — consumer must
        // dead-letter the row rather than re-process forever.
        $outboxId = $this->insertDetailOutboxRow(999999, attempts: 2);

        $this->artisan('pos:consume-order-detail-events')->assertExitCode(1);

        $row = DB::connection('pos')
            ->table('woosoo_order_detail_outbox')
            ->where('id', $outboxId)
            ->first();

        $this->assertNull($row->processed_at);
        $this->assertNotNull($row->failed_at);
        $this->assertSame(3, (int) $row->attempts);
        $this->assertStringContainsString('No DeviceOrder found', (string) $row->last_error);
        Event::assertNotDispatched(OrderDetailsUpdated::class);
    }

    public function test_order_details_updated_broadcasts_only_on_orders_and_admin_channels(): void
    {
        $order = DeviceOrder::factory()->confirmed()->create([
            'order_id' => 900303,
            'device_id' => 77,
        ]);

        $event = new OrderDetailsUpdated($order);
        $channelNames = array_map(
            static fn (Channel $channel): string => $channel->name,
            $event->broadcastOn()
        );

        $this->assertContains('orders.900303', $channelNames, 'must broadcast on the canonical order channel');
        $this->assertContains('admin.orders', $channelNames, 'must broadcast on the admin fan-in channel');
        $this->assertNotContains(
            'device.77',
            $channelNames,
            'detail-update must not fan out on the legacy device channel'
        );
        $this->assertSame('order.details.updated', $event->broadcastAs());
    }

    private function insertDetailOutboxRow(int $posOrderId, int $attempts = 0): int
    {
        return (int) DB::connection('pos')->table('woosoo_order_detail_outbox')->insertGetId([
            'pos_order_id' => $posOrderId,
            'attempts' => $attempts,
            'processed_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertOutboxProcessed(int $outboxId): void
    {
        $row = DB::connection('pos')
            ->table('woosoo_order_detail_outbox')
            ->where('id', $outboxId)
            ->first();

        $this->assertNotNull($row->processed_at);
        $this->assertNull($row->failed_at);
        $this->assertNull($row->last_error);
    }
}
