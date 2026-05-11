<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\Krypton\Menu;
use App\Models\PrintEvent;
use App\Models\RefillSubmission;
use App\Services\DurableRefillGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * WS4: Refill Idempotency Tests
 * 
 * These tests prove that the durable refill submission guard prevents
 * duplicate POS ordered_menu rows and ensures idempotent retries.
 */
class RefillIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private DurableRefillGuard $guard;
    private Device $device;
    private DeviceOrder $order;
    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->guard = app(DurableRefillGuard::class);
        
        // Create test device and order
        $this->device = Device::factory()->create();
        $this->order = DeviceOrder::factory()->create([
            'device_id' => $this->device->id,
            'order_id' => 12345,
            'order_number' => 'TEST-001',
        ]);
        
        // Create test menu item (must be refillable - meat/side)
        $this->menu = Menu::factory()->create([
            'name' => 'Beef Bulgogi',
            'receipt_name' => 'Beef Bulgogi',
            'price' => 350.00,
        ]);
    }

    /** @test */
    public function it_creates_new_submission_when_none_exists()
    {
        $clientSubmissionId = 'test-submission-' . uniqid();
        
        $result = $this->guard->startSubmission(
            $this->device,
            $this->order,
            $clientSubmissionId
        );
        
        $this->assertTrue($result['is_new']);
        $this->assertFalse($result['can_replay']);
        $this->assertFalse($result['is_processing']);
        $this->assertInstanceOf(RefillSubmission::class, $result['submission']);
        
        // Verify DB record
        $submission = RefillSubmission::where([
            'device_id' => $this->device->id,
            'order_id' => $this->order->id,
            'client_submission_id' => $clientSubmissionId,
        ])->first();
        
        $this->assertNotNull($submission);
        $this->assertEquals('PROCESSING', $submission->status);
    }

    /** @test */
    public function it_returns_cached_response_for_completed_submission()
    {
        $clientSubmissionId = 'test-completed-' . uniqid();
        
        // Create completed submission with cached response
        $submission = RefillSubmission::create([
            'device_id' => $this->device->id,
            'order_id' => $this->order->id,
            'client_submission_id' => $clientSubmissionId,
            'status' => 'COMPLETED',
            'response_payload' => ['success' => true, 'order_id' => 12345],
            'response_status' => 200,
        ]);
        
        $result = $this->guard->guard(
            $this->device,
            $this->order,
            $clientSubmissionId
        );
        
        $this->assertFalse($result['proceed']);
        $this->assertNotNull($result['response']);
        $this->assertEquals(200, $result['response']->getStatusCode());
        
        $responseData = json_decode($result['response']->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals(12345, $responseData['order_id']);
    }

    /** @test */
    public function it_returns_409_for_processing_submission()
    {
        $clientSubmissionId = 'test-processing-' . uniqid();
        
        // Create processing submission
        $submission = RefillSubmission::create([
            'device_id' => $this->device->id,
            'order_id' => $this->order->id,
            'client_submission_id' => $clientSubmissionId,
            'status' => 'PROCESSING',
        ]);
        
        $result = $this->guard->guard(
            $this->device,
            $this->order,
            $clientSubmissionId
        );
        
        $this->assertFalse($result['proceed']);
        $this->assertNotNull($result['response']);
        $this->assertEquals(409, $result['response']->getStatusCode());
        
        $responseData = json_decode($result['response']->getContent(), true);
        $this->assertEquals('REFILL_IN_PROGRESS', $responseData['error']['code']);
    }

    /** @test */
    public function it_allows_retry_from_failed_state()
    {
        $clientSubmissionId = 'test-failed-' . uniqid();
        
        // Create failed submission
        $submission = RefillSubmission::create([
            'device_id' => $this->device->id,
            'order_id' => $this->order->id,
            'client_submission_id' => $clientSubmissionId,
            'status' => 'FAILED',
            'error_message' => 'Previous failure',
        ]);
        
        $result = $this->guard->startSubmission(
            $this->device,
            $this->order,
            $clientSubmissionId
        );
        
        $this->assertFalse($result['is_new']);
        $this->assertFalse($result['can_replay']);
        $this->assertFalse($result['is_processing']);
        
        // Status should have transitioned to PROCESSING
        $submission->refresh();
        $this->assertEquals('PROCESSING', $submission->status);
    }

    /** @test */
    public function it_records_pos_ordered_menu_ids_and_prevents_duplicate_inserts()
    {
        $clientSubmissionId = 'test-pos-' . uniqid();
        
        // Start submission
        $result = $this->guard->startSubmission(
            $this->device,
            $this->order,
            $clientSubmissionId
        );
        $submission = $result['submission'];
        
        // Mark POS created with ordered_menu IDs
        $orderedMenuIds = [1001, 1002, 1003];
        $this->guard->markPosCreated($submission, $orderedMenuIds);
        
        $submission->refresh();
        
        // Verify state
        $this->assertEquals('POS_CREATED', $submission->status);
        $this->assertEquals($orderedMenuIds, $submission->pos_ordered_menu_ids);
        
        // Check if POS already done
        $posCheck = $this->guard->checkPosAlreadyDone($submission);
        $this->assertTrue($posCheck['already_done']);
        $this->assertEquals($orderedMenuIds, $posCheck['ordered_menu_ids']);
    }

    /** @test */
    public function it_tracks_state_transitions_correctly()
    {
        $clientSubmissionId = 'test-state-' . uniqid();
        
        $result = $this->guard->startSubmission(
            $this->device,
            $this->order,
            $clientSubmissionId
        );
        $submission = $result['submission'];
        
        // NEW → PROCESSING (happens in startSubmission)
        $this->assertEquals('PROCESSING', $submission->status);
        
        // PROCESSING → POS_CREATED
        $this->guard->markPosCreated($submission, [1001]);
        $submission->refresh();
        $this->assertEquals('POS_CREATED', $submission->status);
        
        // POS_CREATED → MIRRORED
        $this->guard->markMirrored($submission);
        $submission->refresh();
        $this->assertEquals('MIRRORED', $submission->status);
        
        // MIRRORED → PRINT_EVENT_CREATED
        $printEvent = PrintEvent::factory()->create([
            'device_order_id' => $this->order->id,
            'event_type' => 'REFILL',
        ]);
        $this->guard->markPrintEventCreated($submission, $printEvent);
        $submission->refresh();
        $this->assertEquals('PRINT_EVENT_CREATED', $submission->status);
        $this->assertEquals($printEvent->id, $submission->print_event_id);
        
        // PRINT_EVENT_CREATED → COMPLETED
        $this->guard->markCompleted($submission, ['success' => true], 200);
        $submission->refresh();
        $this->assertEquals('COMPLETED', $submission->status);
        $this->assertNotNull($submission->completed_at);
        $this->assertEquals(['success' => true], $submission->response_payload);
    }

    /** @test */
    public function it_prevents_duplicate_submissions_with_same_idempotency_key()
    {
        $clientSubmissionId = 'test-duplicate-' . uniqid();
        
        // Create first submission
        $result1 = $this->guard->startSubmission(
            $this->device,
            $this->order,
            $clientSubmissionId
        );
        $submission1 = $result1['submission'];
        
        // Try to create second submission with same key
        $result2 = $this->guard->startSubmission(
            $this->device,
            $this->order,
            $clientSubmissionId
        );
        $submission2 = $result2['submission'];
        
        // Should be the same submission
        $this->assertEquals($submission1->id, $submission2->id);
        $this->assertFalse($result2['is_new']);
        $this->assertFalse($result2['can_replay']); // Not completed yet
        $this->assertTrue($result2['is_processing']); // Currently processing
    }

    /** @test */
    public function it_allows_different_client_submission_ids_for_different_refills()
    {
        $clientSubmissionId1 = 'test-different-1-' . uniqid();
        $clientSubmissionId2 = 'test-different-2-' . uniqid();
        
        // Create first submission
        $result1 = $this->guard->startSubmission(
            $this->device,
            $this->order,
            $clientSubmissionId1
        );
        $submission1 = $result1['submission'];
        
        // Create second submission with different key
        $result2 = $this->guard->startSubmission(
            $this->device,
            $this->order,
            $clientSubmissionId2
        );
        $submission2 = $result2['submission'];
        
        // Should be different submissions
        $this->assertNotEquals($submission1->id, $submission2->id);
        $this->assertTrue($result1['is_new']);
        $this->assertTrue($result2['is_new']);
    }

    /** @test */
    public function it_marks_submission_as_failed_on_error()
    {
        $clientSubmissionId = 'test-fail-' . uniqid();
        
        $result = $this->guard->startSubmission(
            $this->device,
            $this->order,
            $clientSubmissionId
        );
        $submission = $result['submission'];
        
        $errorMessage = 'Database connection failed';
        $this->guard->markFailed($submission, $errorMessage);
        
        $submission->refresh();
        $this->assertEquals('FAILED', $submission->status);
        $this->assertEquals($errorMessage, $submission->error_message);
        $this->assertNotNull($submission->failed_at);
    }

    /** @test */
    public function it_reuses_existing_print_event_on_retry()
    {
        $clientSubmissionId = 'test-print-reuse-' . uniqid();
        
        // Start submission and complete through print event creation
        $result = $this->guard->startSubmission(
            $this->device,
            $this->order,
            $clientSubmissionId
        );
        $submission = $result['submission'];
        
        // Create print event
        $printEvent = PrintEvent::factory()->create([
            'device_order_id' => $this->order->id,
            'event_type' => 'REFILL',
            'idempotency_key' => "refill:{$this->order->id}:{$clientSubmissionId}",
            'client_submission_id' => $clientSubmissionId,
        ]);
        
        $this->guard->markPrintEventCreated($submission, $printEvent);
        
        $submission->refresh();
        $this->assertEquals($printEvent->id, $submission->print_event_id);
        
        // Verify PrintTicketService would reuse the same event
        $printTicketService = app(\App\Services\PrintTicketService::class);
        $reusedEvent = $printTicketService->createRefillPrintEvent(
            $this->order,
            [], // empty items for test
            $clientSubmissionId
        );
        
        $this->assertEquals($printEvent->id, $reusedEvent->id);
    }

    /** @test */
    public function it_enforces_unique_constraint_on_submissions()
    {
        $clientSubmissionId = 'test-unique-' . uniqid();
        
        // Create first submission
        RefillSubmission::create([
            'device_id' => $this->device->id,
            'order_id' => $this->order->id,
            'client_submission_id' => $clientSubmissionId,
            'status' => 'PROCESSING',
        ]);
        
        // Attempt to create duplicate should throw exception
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        RefillSubmission::create([
            'device_id' => $this->device->id,
            'order_id' => $this->order->id,
            'client_submission_id' => $clientSubmissionId,
            'status' => 'NEW',
        ]);
    }

    /** @test */
    public function it_returns_empty_pos_items_when_no_ordered_menu_ids_recorded()
    {
        $clientSubmissionId = 'test-empty-pos-' . uniqid();
        
        $submission = RefillSubmission::create([
            'device_id' => $this->device->id,
            'order_id' => $this->order->id,
            'client_submission_id' => $clientSubmissionId,
            'status' => 'MIRRORED', // MIRRORED but no pos_ordered_menu_ids
            'pos_ordered_menu_ids' => null,
        ]);
        
        $posCheck = $this->guard->checkPosAlreadyDone($submission);
        $this->assertTrue($posCheck['already_done']);
        $this->assertEmpty($posCheck['ordered_menu_ids']);
    }
}
