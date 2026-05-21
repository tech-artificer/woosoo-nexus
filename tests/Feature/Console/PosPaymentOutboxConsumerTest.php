<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\OrderStatus;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderVoided;
use App\Events\Order\PaymentCompleted;
use App\Events\SessionReset;
use App\Models\DeviceOrder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PosPaymentOutboxConsumerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPosPaymentOutboxTable();
        $this->createPosSessionOutboxTable();
    }

    public function test_consumer_completes_confirmed_local_order_dispatches_realtime_events_and_marks_row_processed(): void
    {
        Event::fake([
            OrderStatusUpdated::class,
            OrderCompleted::class,
            PaymentCompleted::class,
            SessionReset::class,
        ]);

        $order = $this->confirmedOrder([
            'order_id' => 900101,
            'session_id' => 4242,
        ]);
        $outboxId = $this->insertOutboxRow($order, OrderStatus::COMPLETED->value);

        $this->artisan('pos:consume-payment-status-events')->assertExitCode(0);

        $this->assertSame(OrderStatus::COMPLETED, $order->refresh()->status);
        $this->assertOutboxProcessed($outboxId);

        Event::assertDispatched(OrderStatusUpdated::class, fn ($event): bool => $event->order->id === $order->id);
        Event::assertDispatched(OrderCompleted::class, fn ($event): bool => $event->deviceOrder->id === $order->id);
        Event::assertDispatched(PaymentCompleted::class, fn ($event): bool => $event->deviceOrder->id === $order->id);
        Event::assertNotDispatched(SessionReset::class);
    }

    public function test_consumer_voids_confirmed_local_order_for_voided_target_status(): void
    {
        Event::fake([
            OrderStatusUpdated::class,
            OrderVoided::class,
            OrderCompleted::class,
            PaymentCompleted::class,
            SessionReset::class,
        ]);

        $order = $this->confirmedOrder([
            'order_id' => 900102,
            'session_id' => 5252,
        ]);
        $outboxId = $this->insertOutboxRow($order, OrderStatus::VOIDED->value);

        $this->artisan('pos:consume-payment-status-events')->assertExitCode(0);

        $this->assertSame(OrderStatus::VOIDED, $order->refresh()->status);
        $this->assertOutboxProcessed($outboxId);

        Event::assertDispatched(OrderStatusUpdated::class, fn ($event): bool => $event->order->id === $order->id);
        Event::assertDispatched(OrderVoided::class, fn ($event): bool => $event->deviceOrder->id === $order->id);
        Event::assertNotDispatched(SessionReset::class);
        Event::assertNotDispatched(OrderCompleted::class);
        Event::assertNotDispatched(PaymentCompleted::class);
    }

    public function test_consumer_does_not_dispatch_session_reset_when_one_order_in_shared_pos_session_completes(): void
    {
        Event::fake([
            OrderStatusUpdated::class,
            OrderCompleted::class,
            PaymentCompleted::class,
            SessionReset::class,
        ]);

        $firstOrder = $this->confirmedOrder([
            'order_id' => 900201,
            'session_id' => 4242,
        ]);
        $secondOrder = $this->confirmedOrder([
            'order_id' => 900202,
            'session_id' => 4242,
        ]);
        $thirdOrder = $this->confirmedOrder([
            'order_id' => 900203,
            'session_id' => 4242,
        ]);

        $this->insertOutboxRow($secondOrder, OrderStatus::COMPLETED->value);

        $this->artisan('pos:consume-payment-status-events')->assertExitCode(0);

        $this->assertSame(OrderStatus::CONFIRMED, $firstOrder->refresh()->status);
        $this->assertSame(OrderStatus::COMPLETED, $secondOrder->refresh()->status);
        $this->assertSame(OrderStatus::CONFIRMED, $thirdOrder->refresh()->status);

        Event::assertDispatched(OrderCompleted::class, fn ($event): bool => $event->deviceOrder->id === $secondOrder->id);
        Event::assertNotDispatched(SessionReset::class);
    }

    public function test_consumer_dispatches_session_reset_for_pos_session_close_outbox_row(): void
    {
        Event::fake([
            OrderStatusUpdated::class,
            OrderCompleted::class,
            PaymentCompleted::class,
            OrderVoided::class,
            SessionReset::class,
        ]);

        $outboxId = $this->insertSessionOutboxRow(4242);

        $this->artisan('pos:consume-payment-status-events')->assertExitCode(0);

        $this->assertSessionOutboxProcessed($outboxId);

        Event::assertDispatched(SessionReset::class, fn (SessionReset $event): bool => $event->sessionId === 4242);
        Event::assertNotDispatched(OrderStatusUpdated::class);
        Event::assertNotDispatched(OrderCompleted::class);
        Event::assertNotDispatched(PaymentCompleted::class);
        Event::assertNotDispatched(OrderVoided::class);
    }

    public function test_consumer_does_not_overwrite_already_terminal_local_order(): void
    {
        Event::fake([
            OrderStatusUpdated::class,
            OrderVoided::class,
            OrderCompleted::class,
            PaymentCompleted::class,
            SessionReset::class,
        ]);

        $order = DeviceOrder::factory()->completed()->create([
            'order_id' => 900103,
            'session_id' => 6262,
        ]);
        $this->insertOutboxRow($order, OrderStatus::VOIDED->value);

        $this->artisan('pos:consume-payment-status-events')->assertExitCode(0);

        $this->assertSame(OrderStatus::COMPLETED, $order->refresh()->status);
        Event::assertNotDispatched(OrderStatusUpdated::class);
        Event::assertNotDispatched(OrderVoided::class);
        Event::assertNotDispatched(SessionReset::class);
    }

    public function test_failed_finalization_keeps_row_retryable_and_increments_attempts(): void
    {
        Event::fakeExcept([
            OrderStatusUpdated::class,
            OrderCompleted::class,
            PaymentCompleted::class,
            SessionReset::class,
        ]);
        Event::listen(OrderStatusUpdated::class, static function (): void {
            throw new \RuntimeException('synthetic broadcast failure');
        });

        $order = $this->confirmedOrder([
            'order_id' => 900104,
            'session_id' => 7272,
        ]);
        $outboxId = $this->insertOutboxRow($order, OrderStatus::COMPLETED->value);

        $this->artisan('pos:consume-payment-status-events')->assertExitCode(1);

        $outbox = DB::connection('pos')
            ->table('woosoo_order_status_outbox')
            ->where('id', $outboxId)
            ->first();

        $this->assertSame(OrderStatus::CONFIRMED, $order->refresh()->status);
        $this->assertNull($outbox->processed_at);
        $this->assertNull($outbox->failed_at);
        $this->assertSame(1, (int) $outbox->attempts);
        $this->assertStringContainsString('synthetic broadcast failure', (string) $outbox->last_error);
    }

    public function test_failed_finalization_dead_letters_row_after_fifth_attempt(): void
    {
        Event::fakeExcept([
            OrderStatusUpdated::class,
            OrderCompleted::class,
            PaymentCompleted::class,
            SessionReset::class,
        ]);
        Event::listen(OrderStatusUpdated::class, static function (): void {
            throw new \RuntimeException('synthetic permanent broadcast failure');
        });

        $order = $this->confirmedOrder([
            'order_id' => 900105,
            'session_id' => 8282,
        ]);
        $outboxId = $this->insertOutboxRow($order, OrderStatus::COMPLETED->value, attempts: 4);

        $this->artisan('pos:consume-payment-status-events')->assertExitCode(1);

        $outbox = DB::connection('pos')
            ->table('woosoo_order_status_outbox')
            ->where('id', $outboxId)
            ->first();

        $this->assertSame(OrderStatus::CONFIRMED, $order->refresh()->status);
        $this->assertNull($outbox->processed_at);
        $this->assertSame(5, (int) $outbox->attempts);
        $this->assertNotNull($outbox->failed_at);
        $this->assertStringContainsString('synthetic permanent broadcast failure', (string) $outbox->last_error);
    }

    private function confirmedOrder(array $attributes = []): DeviceOrder
    {
        return DeviceOrder::factory()->confirmed()->create($attributes);
    }

    private function createPosPaymentOutboxTable(): void
    {
        $schema = Schema::connection('pos');

        $schema->dropIfExists('woosoo_order_status_outbox');
        $schema->create('woosoo_order_status_outbox', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('pos_order_id');
            $table->string('target_status');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    private function createPosSessionOutboxTable(): void
    {
        $schema = Schema::connection('pos');

        $schema->dropIfExists('woosoo_session_status_outbox');
        $schema->create('woosoo_session_status_outbox', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('pos_session_id');
            $table->string('event_type');
            $table->timestamp('date_time_closed')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    private function insertOutboxRow(DeviceOrder $order, string $targetStatus, int $attempts = 0): int
    {
        return (int) DB::connection('pos')->table('woosoo_order_status_outbox')->insertGetId([
            'pos_order_id' => $order->order_id,
            'target_status' => $targetStatus,
            'attempts' => $attempts,
            'processed_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertSessionOutboxRow(int $sessionId, int $attempts = 0): int
    {
        return (int) DB::connection('pos')->table('woosoo_session_status_outbox')->insertGetId([
            'pos_session_id' => $sessionId,
            'event_type' => 'closed',
            'date_time_closed' => now(),
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
        $outbox = DB::connection('pos')
            ->table('woosoo_order_status_outbox')
            ->where('id', $outboxId)
            ->first();

        $this->assertNotNull($outbox->processed_at);
        $this->assertNull($outbox->failed_at);
        $this->assertNull($outbox->last_error);
    }

    private function assertSessionOutboxProcessed(int $outboxId): void
    {
        $outbox = DB::connection('pos')
            ->table('woosoo_session_status_outbox')
            ->where('id', $outboxId)
            ->first();

        $this->assertNotNull($outbox->processed_at);
        $this->assertNull($outbox->failed_at);
        $this->assertNull($outbox->last_error);
    }
}
