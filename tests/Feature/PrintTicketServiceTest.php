<?php

namespace Tests\Feature;

use App\Enums\PrintEventType;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\Krypton\Menu;
use App\Models\PrintEvent;
use App\Models\PrintEventItem;
use App\Services\PrintTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Traits\MocksKryptonSession;
use Tests\TestCase;

class PrintTicketServiceTest extends TestCase
{
    use RefreshDatabase, MocksKryptonSession;

    private PrintTicketService $service;
    private Device $device;
    private DeviceOrder $order;
    private array $testItems;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(PrintTicketService::class);
        
        // Create test data
        $this->mockActiveKryptonSession();

        $this->device = Device::factory()->withTable(1)->create();
        $this->order = DeviceOrder::factory()->create([
            'device_id' => $this->device->id,
            'order_id' => 12345,
            'order_number' => 'TEST-001',
        ]);
        
        // Create test menu items
        $menus = Menu::factory()->count(3)->create();
        
        // Create test order items
        $this->testItems = [];
        foreach ($menus as $index => $menu) {
            $item = DeviceOrderItems::factory()->create([
                'order_id' => $this->order->id,
                'menu_id' => $menu->id,
                'quantity' => ($index + 1) * 2,
                'is_refill' => false,
            ]);
            $this->testItems[] = $item;
        }
    }

    /** @test */
    public function it_creates_initial_print_event_with_idempotency()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        $printEvent = $this->service->createInitialPrintEvent($this->order, $clientSubmissionId);
        
        $this->assertInstanceOf(PrintEvent::class, $printEvent);
        $this->assertEquals(PrintEventType::INITIAL->value, $printEvent->event_type);
        $this->assertEquals("initial:{$this->order->id}:{$clientSubmissionId}", $printEvent->idempotency_key);
        $this->assertEquals($clientSubmissionId, $printEvent->client_submission_id);
        $this->assertNull($printEvent->refill_number);
        
        // Check that items were attached
        $this->assertCount(3, $printEvent->printEventItems);
        
        // Check that items have client submission ID
        foreach ($this->testItems as $item) {
            $item->refresh();
            $this->assertEquals($clientSubmissionId, $item->client_submission_id);
        }
    }

    /** @test */
    public function it_reuses_existing_initial_print_event_with_same_idempotency_key()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        // Create first print event
        $firstEvent = $this->service->createInitialPrintEvent($this->order, $clientSubmissionId);
        $firstEventId = $firstEvent->id;
        
        // Try to create again with same client submission ID
        $secondEvent = $this->service->createInitialPrintEvent($this->order, $clientSubmissionId);
        
        $this->assertEquals($firstEventId, $secondEvent->id);
        $this->assertEquals($firstEvent->idempotency_key, $secondEvent->idempotency_key);
    }

    /** @test */
    public function it_creates_refill_print_event_with_idempotency()
    {
        $clientSubmissionId = Str::uuid()->toString();
        $refillItems = [
            ['menu_id' => $this->testItems[0]->menu_id, 'quantity' => 2],
            ['menu_id' => $this->testItems[1]->menu_id, 'quantity' => 1],
        ];
        
        // Create refill items in the database
        foreach ($refillItems as $itemData) {
            DeviceOrderItems::factory()->create([
                'order_id' => $this->order->id,
                'menu_id' => $itemData['menu_id'],
                'quantity' => $itemData['quantity'],
                'is_refill' => true,
            ]);
        }
        
        $printEvent = $this->service->createRefillPrintEvent($this->order, $refillItems, $clientSubmissionId);
        
        $this->assertInstanceOf(PrintEvent::class, $printEvent);
        $this->assertEquals(PrintEventType::REFILL->value, $printEvent->event_type);
        $this->assertEquals("refill:{$this->order->id}:{$clientSubmissionId}", $printEvent->idempotency_key);
        $this->assertEquals($clientSubmissionId, $printEvent->client_submission_id);
        $this->assertEquals(1, $printEvent->refill_number); // First refill
        
        // Check that refill items were attached
        $this->assertCount(2, $printEvent->printEventItems);
    }

    /** @test */
    public function it_increments_refill_number_for_multiple_refills()
    {
        // Create first refill
        $clientSubmissionId1 = Str::uuid()->toString();
        $refillItems = [['menu_id' => $this->testItems[0]->menu_id, 'quantity' => 2]];
        
        DeviceOrderItems::factory()->create([
            'order_id' => $this->order->id,
            'menu_id' => $refillItems[0]['menu_id'],
            'quantity' => $refillItems[0]['quantity'],
            'is_refill' => true,
        ]);
        
        $firstEvent = $this->service->createRefillPrintEvent($this->order, $refillItems, $clientSubmissionId1);
        $this->assertEquals(1, $firstEvent->refill_number);
        
        // Create second refill
        $clientSubmissionId2 = Str::uuid()->toString();
        DeviceOrderItems::factory()->create([
            'order_id' => $this->order->id,
            'menu_id' => $this->testItems[1]->menu_id,
            'quantity' => 1,
            'is_refill' => true,
        ]);
        
        $secondRefillItems = [['menu_id' => $this->testItems[1]->menu_id, 'quantity' => 1]];
        $secondEvent = $this->service->createRefillPrintEvent($this->order, $secondRefillItems, $clientSubmissionId2);
        
        $this->assertEquals(2, $secondEvent->refill_number);
    }

    /** @test */
    public function it_reuses_existing_refill_print_event_with_same_idempotency_key()
    {
        $clientSubmissionId = Str::uuid()->toString();
        $refillItems = [['menu_id' => $this->testItems[0]->menu_id, 'quantity' => 2]];
        
        DeviceOrderItems::factory()->create([
            'order_id' => $this->order->id,
            'menu_id' => $refillItems[0]['menu_id'],
            'quantity' => $refillItems[0]['quantity'],
            'is_refill' => true,
        ]);
        
        // Create first refill print event
        $firstEvent = $this->service->createRefillPrintEvent($this->order, $refillItems, $clientSubmissionId);
        $firstEventId = $firstEvent->id;
        
        // Try to create again with same client submission ID
        $secondEvent = $this->service->createRefillPrintEvent($this->order, $refillItems, $clientSubmissionId);
        
        $this->assertEquals($firstEventId, $secondEvent->id);
        $this->assertEquals($firstEvent->idempotency_key, $secondEvent->idempotency_key);
    }

    /** @test */
    public function it_marks_items_as_printed_when_acknowledged()
    {
        $clientSubmissionId = Str::uuid()->toString();
        $printEvent = $this->service->createInitialPrintEvent($this->order, $clientSubmissionId);
        
        // Verify items are not printed initially
        foreach ($this->testItems as $item) {
            $item->refresh();
            $this->assertFalse($item->is_printed);
            $this->assertNull($item->printed_at);
            $this->assertNull($item->printed_by_print_event_id);
        }
        
        // Mark items as printed
        $this->service->markItemsAsPrinted($printEvent);
        
        // Verify items are now marked as printed
        foreach ($this->testItems as $item) {
            $item->refresh();
            $this->assertTrue($item->is_printed);
            $this->assertNotNull($item->printed_at);
            $this->assertEquals($printEvent->id, $item->printed_by_print_event_id);
            $this->assertEquals(PrintEventType::INITIAL->value, $item->print_type);
        }
    }

    /** @test */
    public function it_handles_refill_items_marking_as_printed()
    {
        $clientSubmissionId = Str::uuid()->toString();
        $refillItems = [['menu_id' => $this->testItems[0]->menu_id, 'quantity' => 2]];
        
        $refillItem = DeviceOrderItems::factory()->create([
            'order_id' => $this->order->id,
            'menu_id' => $refillItems[0]['menu_id'],
            'quantity' => $refillItems[0]['quantity'],
            'is_refill' => true,
        ]);
        
        $printEvent = $this->service->createRefillPrintEvent($this->order, $refillItems, $clientSubmissionId);
        
        // Mark refill items as printed
        $this->service->markItemsAsPrinted($printEvent);
        
        // Verify refill item is marked as printed
        $refillItem->refresh();
        $this->assertTrue($refillItem->is_printed);
        $this->assertEquals($printEvent->id, $refillItem->printed_by_print_event_id);
        $this->assertEquals(PrintEventType::REFILL->value, $refillItem->print_type);
        
        // Verify initial items are still not printed
        foreach ($this->testItems as $item) {
            $item->refresh();
            $this->assertFalse($item->is_printed);
        }
    }

    /** @test */
    public function it_prevents_duplicate_print_events_for_same_submission()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        // Create initial print event
        $initialEvent = $this->service->createInitialPrintEvent($this->order, $clientSubmissionId);
        
        // Try to create another initial event with same submission ID
        $duplicateEvent = $this->service->createInitialPrintEvent($this->order, $clientSubmissionId);
        
        // Should return the same event
        $this->assertEquals($initialEvent->id, $duplicateEvent->id);
        
        // Verify only one print event exists
        $this->assertEquals(1, PrintEvent::where('client_submission_id', $clientSubmissionId)->count());
    }

    /** @test */
    public function it_allows_different_submission_ids_for_same_order()
    {
        $clientSubmissionId1 = Str::uuid()->toString();
        $clientSubmissionId2 = Str::uuid()->toString();
        
        // Create two different initial print events
        $event1 = $this->service->createInitialPrintEvent($this->order, $clientSubmissionId1);
        $event2 = $this->service->createInitialPrintEvent($this->order, $clientSubmissionId2);
        
        // Should create different events
        $this->assertNotEquals($event1->id, $event2->id);
        $this->assertNotEquals($event1->idempotency_key, $event2->idempotency_key);
        
        // Verify both events exist
        $this->assertEquals(2, PrintEvent::where('device_order_id', $this->order->id)->count());
    }

    /** @test */
    public function it_logs_warning_when_legacy_fallback_is_used_for_initial_order()
    {
        Log::spy();

        // Test legacy fallback path by calling OrderService without client_submission_id
        $orderService = app(\App\Services\Krypton\OrderService::class);
        $result = $orderService->processOrder($this->device, [
            'package_id' => 1,
            'guest_count' => 2,
            'items' => [['menu_id' => 1, 'quantity' => 1]],
        ], null); // No client_submission_id

        $this->assertInstanceOf(\App\Models\DeviceOrder::class, $result);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Legacy non-idempotent print event path used', [
                'device_order_id' => $result->id,
                'event_type' => 'INITIAL',
                'reason' => 'No client_submission_id provided',
            ]);
    }

    /** @test */
    public function it_uses_idempotent_path_when_client_submission_id_exists()
    {
        Log::spy();

        $clientSubmissionId = Str::uuid()->toString();
        
        // Test idempotent path by calling OrderService with client_submission_id
        $orderService = app(\App\Services\Krypton\OrderService::class);
        $result = $orderService->processOrder($this->device, [
            'package_id' => 1,
            'guest_count' => 2,
            'items' => [['menu_id' => 1, 'quantity' => 1]],
        ], $clientSubmissionId);

        $this->assertInstanceOf(\App\Models\DeviceOrder::class, $result);
        
        // Verify print event was created with idempotency
        $printEvent = PrintEvent::where('client_submission_id', $clientSubmissionId)->first();
        $this->assertNotNull($printEvent);
        $this->assertEquals("initial:{$result->id}:{$clientSubmissionId}", $printEvent->idempotency_key);
        Log::shouldNotHaveReceived('warning', [
            'Legacy non-idempotent print event path used',
            \Mockery::any(),
        ]);
    }
}
