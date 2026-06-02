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

        // POS source tables the consumer re-reads to refresh authoritative
        // detail values (guest_count on `orders`, totals on `order_checks`).
        $schema->dropIfExists('orders');
        $schema->create('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedInteger('guest_count')->nullable();
        });
        $schema->dropIfExists('order_checks');
        $schema->create('order_checks', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('order_id')->index();
            $table->decimal('subtotal_amount', 12, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
        });
    }

    public function test_consumer_refreshes_pos_detail_values_before_dispatching(): void
    {
        Event::fake([OrderDetailsUpdated::class]);

        // Local row holds the PRE-edit values.
        $order = DeviceOrder::factory()->confirmed()->create([
            'order_id'    => 900301,
            'guest_count' => 2,
            'subtotal'    => 90.00,
            'tax'         => 10.00,
            'discount'    => 0.00,
            'total'       => 100.00,
        ]);

        // POS cashier edits the order: +2 guests and new totals.
        $this->seedPosOrder(900301, guestCount: 4);
        $this->seedPosCheck(900301, subtotal: 180.00, tax: 20.00, discount: 5.00, total: 195.00);

        $outboxId = $this->insertDetailOutboxRow($order->order_id);

        $this->artisan('pos:consume-order-detail-events')->assertExitCode(0);

        $this->assertOutboxProcessed($outboxId);

        // The local row must be refreshed from POS (the fix) — not left stale.
        $order->refresh();
        $this->assertSame(4, (int) $order->guest_count);
        $this->assertEquals(180.00, (float) $order->subtotal);
        $this->assertEquals(20.00, (float) $order->tax);
        $this->assertEquals(5.00, (float) $order->discount);
        $this->assertEquals(195.00, (float) $order->total);

        // And the broadcast must carry the fresh values, not the stale ones.
        Event::assertDispatched(
            OrderDetailsUpdated::class,
            fn (OrderDetailsUpdated $event): bool => $event->order->order_id === $order->order_id
                && (int) $event->order->guest_count === 4
                && (float) $event->order->total === 195.00
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

    private function seedPosOrder(int $posOrderId, int $guestCount): void
    {
        DB::connection('pos')->table('orders')->insert([
            'id'          => $posOrderId,
            'guest_count' => $guestCount,
        ]);
    }

    private function seedPosCheck(int $posOrderId, float $subtotal, float $tax, float $discount, float $total): void
    {
        DB::connection('pos')->table('order_checks')->insert([
            'order_id'        => $posOrderId,
            'subtotal_amount' => $subtotal,
            'tax_amount'      => $tax,
            'discount_amount' => $discount,
            'total_amount'    => $total,
        ]);
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
