<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\Krypton\Menu;
use App\Models\PrintEvent;
use App\Models\RefillSubmission;
use App\Services\RefillSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Refill Idempotency Tests
 * 
 * Verifies that refill submissions cannot duplicate POS ordered_menu rows
 * even when retries occur after partial failures.
 */
class RefillIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;
    private DeviceOrder $deviceOrder;
    private RefillSubmissionService $submissionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->submissionService = app(RefillSubmissionService::class);
        
        $this->device = Device::factory()->create([
            'security_code' => 'test-token',
        ]);
        
        $this->deviceOrder = DeviceOrder::factory()->create([
            'device_id' => $this->device->id,
            'order_id' => 12345,
            'order_number' => 'TEST-001',
            'session_id' => 'test-session',
        ]);
    }

    /** @test */
    public function it_creates_new_submission_for_first_request()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $this->assertEquals('new', $result['status']);
        $this->assertInstanceOf(RefillSubmission::class, $result['submission']);
        $this->assertEquals('PROCESSING', $result['submission']->status);
        $this->assertEquals($this->device->id, $result['submission']->device_id);
        $this->assertEquals($this->deviceOrder->id, $result['submission']->device_order_id);
        $this->assertEquals($clientSubmissionId, $result['submission']->client_submission_id);
    }

    /** @test */
    public function it_returns_completed_status_for_existing_completed_submission()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        // Create and complete a submission
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $submission = $result['submission'];
        $this->submissionService->markPosCreated($submission, [100, 101]);
        $this->submissionService->markMirrored($submission);
        $this->submissionService->markPrintEventCreated($submission);
        
        $cachedResponse = ['success' => true, 'order_id' => $this->deviceOrder->id];
        $this->submissionService->completeSubmission($submission, $cachedResponse);
        
        // Second request should return completed status with cached response
        $secondResult = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $this->assertEquals('completed', $secondResult['status']);
        $this->assertEquals($cachedResponse, $secondResult['response']);
    }

    /** @test */
    public function it_returns_conflict_status_for_processing_submission()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        // Create submission
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        // Mark as POS_CREATED (in-flight)
        $this->submissionService->markPosCreated($result['submission'], [100]);
        
        // Second request should return conflict
        $secondResult = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $this->assertEquals('conflict', $secondResult['status']);
        $this->assertEquals('POS_CREATED', $secondResult['submission']->status);
    }

    /** @test */
    public function it_allows_retry_for_failed_submission()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        // Create and fail a submission
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $this->submissionService->markFailed($result['submission'], 'Local mirror failed');
        
        // Retry should allow new processing
        $retryResult = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $this->assertEquals('new', $retryResult['status']);
        $this->assertEquals('PROCESSING', $retryResult['submission']->status);
        // failed_at is cleared on retry since we're starting fresh
        $this->assertNull($retryResult['submission']->failed_at);
    }

    /** @test */
    public function it_allows_different_client_submission_ids_for_same_order()
    {
        $clientSubmissionId1 = Str::uuid()->toString();
        $clientSubmissionId2 = Str::uuid()->toString();
        
        // First submission
        $result1 = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId1
        );
        
        // Complete first submission
        $this->submissionService->markPosCreated($result1['submission'], [100]);
        $this->submissionService->markMirrored($result1['submission']);
        $this->submissionService->completeSubmission($result1['submission'], ['success' => true]);
        
        // Different submission ID should create new submission
        $result2 = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId2
        );
        
        $this->assertEquals('new', $result2['status']);
        $this->assertNotEquals($result1['submission']->id, $result2['submission']->id);
    }

    /** @test */
    public function it_stores_pos_ordered_menu_ids_for_idempotency_verification()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $posOrderedMenuIds = [100, 101, 102];
        $this->submissionService->markPosCreated($result['submission'], $posOrderedMenuIds);
        
        $result['submission']->refresh();
        $this->assertEquals($posOrderedMenuIds, $result['submission']->pos_ordered_menu_ids);
    }

    /** @test */
    public function it_verifies_pos_result_matches_stored_ids()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $storedIds = [100, 101];
        $this->submissionService->markPosCreated($result['submission'], $storedIds);
        
        // Same IDs should match
        $this->assertTrue($this->submissionService->verifyPosResultMatches(
            $result['submission'],
            [100, 101]
        ));
        
        // Different IDs should not match
        $this->assertFalse($this->submissionService->verifyPosResultMatches(
            $result['submission'],
            [100, 102]
        ));
    }

    /** @test */
    public function it_transitions_through_state_machine_correctly()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $submission = $result['submission'];
        
        // NEW (via factory) → PROCESSING (on creation)
        $this->assertEquals('PROCESSING', $submission->status);
        
        // PROCESSING → POS_CREATED
        $this->assertTrue($this->submissionService->markPosCreated($submission, [100]));
        $submission->refresh();
        $this->assertEquals('POS_CREATED', $submission->status);
        $this->assertNotNull($submission->pos_created_at);
        
        // POS_CREATED → MIRRORED
        $this->assertTrue($this->submissionService->markMirrored($submission));
        $submission->refresh();
        $this->assertEquals('MIRRORED', $submission->status);
        $this->assertNotNull($submission->mirrored_at);
        
        // MIRRORED → PRINT_EVENT_CREATED
        $this->assertTrue($this->submissionService->markPrintEventCreated($submission));
        $submission->refresh();
        $this->assertEquals('PRINT_EVENT_CREATED', $submission->status);
        $this->assertNotNull($submission->print_event_created_at);
        
        // PRINT_EVENT_CREATED → COMPLETED
        $this->assertTrue($this->submissionService->completeSubmission($submission, ['success' => true]));
        $submission = $submission->fresh(); // Get fully refreshed model
        $this->assertEquals('COMPLETED', $submission->status);
        $this->assertNotNull($submission->completed_at);
    }

    /** @test */
    public function it_caches_response_on_completion()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $cachedResponse = [
            'success' => true,
            'order' => ['id' => $this->deviceOrder->id],
            'created' => [['id' => 100]],
        ];
        
        $this->submissionService->markPosCreated($result['submission'], [100]);
        $this->submissionService->markMirrored($result['submission']);
        $this->submissionService->completeSubmission($result['submission'], $cachedResponse);
        
        $result['submission']->refresh();
        $this->assertEquals($cachedResponse, $result['submission']->response_payload);
    }

    /** @test */
    public function it_detects_stale_lock_and_allows_retry()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        // Create submission with old processing timestamp
        $submission = RefillSubmission::create([
            'device_id' => $this->device->id,
            'device_order_id' => $this->deviceOrder->id,
            'client_submission_id' => $clientSubmissionId,
            'status' => 'PROCESSING',
            'processing_started_at' => now()->subSeconds(400), // Beyond 300s timeout
        ]);
        
        // Should allow retry due to stale lock
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $this->assertEquals('new', $result['status']);
        $this->assertEquals('PROCESSING', $result['submission']->status);
        $this->assertGreaterThan($submission->processing_started_at, $result['submission']->processing_started_at);
    }

    /** @test */
    public function it_prevents_duplicate_pos_insert_on_concurrent_requests()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        // First request acquires lock
        $result1 = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        // Mark as POS_CREATED to simulate successful POS insert
        $this->submissionService->markPosCreated($result1['submission'], [100, 101]);
        
        // Second concurrent request should detect existing submission and return conflict
        $result2 = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $this->assertEquals('conflict', $result2['status']);
        
        // Only one submission should exist in database
        $this->assertEquals(1, RefillSubmission::where([
            'device_id' => $this->device->id,
            'device_order_id' => $this->deviceOrder->id,
            'client_submission_id' => $clientSubmissionId,
        ])->count());
    }

    /** @test */
    public function it_marks_failed_and_allows_recovery()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $submission = $result['submission'];
        
        // Mark POS created then fail
        $this->submissionService->markPosCreated($submission, [100]);
        $this->submissionService->markFailed($submission, 'Connection timeout during local mirror');
        
        $submission->refresh();
        $this->assertEquals('FAILED', $submission->status);
        $this->assertNotNull($submission->failed_at);
        $this->assertNotNull($submission->error_message);
        $this->assertStringContainsString('Connection timeout', $submission->error_message);
    }

    /** @test */
    public function it_allows_new_submission_after_failure_for_retry()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        // First attempt - create and fail
        $result1 = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $this->submissionService->markPosCreated($result1['submission'], [100]);
        $this->submissionService->markFailed($result1['submission'], 'Local mirror failed');
        
        // Retry - should reset to PROCESSING
        $result2 = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $this->assertEquals('new', $result2['status']);
        $this->assertEquals('PROCESSING', $result2['submission']->status);
        
        // Complete retry successfully
        $this->submissionService->markPosCreated($result2['submission'], [100]);
        $this->submissionService->markMirrored($result2['submission']);
        $this->submissionService->completeSubmission($result2['submission'], ['success' => true]);
        
        // Verify final state
        $result2['submission']->refresh();
        $this->assertEquals('COMPLETED', $result2['submission']->status);
    }

    /** @test */
    public function it_enforces_unique_constraint_on_device_order_submission()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        // Create first submission (with active lock)
        RefillSubmission::create([
            'device_id' => $this->device->id,
            'device_order_id' => $this->deviceOrder->id,
            'client_submission_id' => $clientSubmissionId,
            'status' => 'PROCESSING',
            'processing_started_at' => now(),
        ]);
        
        // Attempting to create duplicate should result in finding existing
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $this->assertEquals('conflict', $result['status']); // Should find existing and report conflict
        $this->assertEquals(1, RefillSubmission::where([
            'device_id' => $this->device->id,
            'device_order_id' => $this->deviceOrder->id,
            'client_submission_id' => $clientSubmissionId,
        ])->count());
    }

    /** @test */
    public function it_returns_null_response_for_non_completed_submission()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        $this->assertNull($result['response']);
    }

    /** @test */
    public function it_handles_empty_pos_ordered_menu_ids_in_verification()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        // No POS IDs stored yet - should return true (no previous data)
        $this->assertTrue($this->submissionService->verifyPosResultMatches(
            $result['submission'],
            [100, 101]
        ));
    }

    /** @test */
    public function it_completes_submission_without_state_transition_if_already_completed()
    {
        $clientSubmissionId = Str::uuid()->toString();
        
        $result = $this->submissionService->acquireOrFindSubmission(
            $this->device,
            $this->deviceOrder,
            $clientSubmissionId
        );
        
        // Complete once
        $this->submissionService->markPosCreated($result['submission'], [100]);
        $this->submissionService->markMirrored($result['submission']);
        $this->submissionService->completeSubmission($result['submission'], ['success' => true]);
        
        // Calling complete again should still succeed
        $newResponse = ['success' => true, 'updated' => true];
        $this->assertTrue($this->submissionService->completeSubmission($result['submission'], $newResponse));
        
        $result['submission']->refresh();
        $this->assertEquals($newResponse, $result['submission']->response_payload);
    }
}
