<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates durable refill submission guard table.
     * Tracks state machine: NEW → PROCESSING → POS_CREATED → MIRRORED → PRINT_EVENT_CREATED → COMPLETED
     */
    public function up(): void
    {
        Schema::create('refill_submissions', function (Blueprint $table) {
            $table->id();
            
            // Idempotency scope: device + order + client submission
            $table->unsignedBigInteger('device_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('client_submission_id', 64)->index();
            
            // Unique constraint prevents duplicate submissions
            $table->unique(['device_id', 'order_id', 'client_submission_id'], 'refill_submission_unique');
            
            // State machine tracking
            $table->string('status', 32)->default('NEW')->index();
            // NEW → PROCESSING → POS_CREATED → MIRRORED → PRINT_EVENT_CREATED → COMPLETED
            // Can also transition to FAILED from any state
            
            // Foreign keys to track what was created
            $table->unsignedBigInteger('print_event_id')->nullable()->index();
            
            // POS tracking - store ordered_menu IDs to prevent duplicate POS inserts
            $table->json('pos_ordered_menu_ids')->nullable();
            
            // Response caching for replay
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            
            // Error tracking for FAILED state
            $table->text('error_message')->nullable();
            
            // Timestamps for each state transition (for debugging/auditing)
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('pos_created_at')->nullable();
            $table->timestamp('mirrored_at')->nullable();
            $table->timestamp('print_event_created_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('device_orders')->onDelete('cascade');
            $table->foreign('print_event_id')->references('id')->on('print_events')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refill_submissions');
    }
};
