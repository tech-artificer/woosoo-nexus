<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\RefillSubmission;
use App\Models\Device;
use App\Models\DeviceOrder;

class RefillSubmissionFactory extends Factory
{
    protected $model = RefillSubmission::class;

    public function definition()
    {
        return [
            'device_id' => Device::factory(),
            'device_order_id' => DeviceOrder::factory(),
            'client_submission_id' => $this->faker->uuid,
            'status' => $this->faker->randomElement(['NEW', 'PROCESSING', 'POS_CREATED', 'MIRRORED', 'PRINT_EVENT_CREATED', 'COMPLETED', 'FAILED']),
            'print_event_id' => null,
            'pos_ordered_menu_ids' => null,
            'response_payload' => null,
            'response_status' => null,
            'error_message' => null,
            'processing_started_at' => now(),
        ];
    }

    public function new(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'NEW',
                'processing_started_at' => null,
            ];
        });
    }

    public function processing(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'PROCESSING',
                'processing_started_at' => now(),
            ];
        });
    }

    public function posCreated(array $orderedMenuIds = []): self
    {
        return $this->state(function (array $attributes) use ($orderedMenuIds) {
            return [
                'status' => 'POS_CREATED',
                'pos_ordered_menu_ids' => $orderedMenuIds,
                'pos_created_at' => now(),
            ];
        });
    }

    public function mirrored(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'MIRRORED',
                'mirrored_at' => now(),
            ];
        });
    }

    public function printEventCreated(int $printEventId): self
    {
        return $this->state(function (array $attributes) use ($printEventId) {
            return [
                'status' => 'PRINT_EVENT_CREATED',
                'print_event_id' => $printEventId,
                'print_event_created_at' => now(),
            ];
        });
    }

    public function completed(array $responsePayload = [], int $status = 200): self
    {
        return $this->state(function (array $attributes) use ($responsePayload, $status) {
            return [
                'status' => 'COMPLETED',
                'response_payload' => $responsePayload,
                'response_status' => $status,
                'completed_at' => now(),
            ];
        });
    }

    public function failed(string $errorMessage = ''): self
    {
        return $this->state(function (array $attributes) use ($errorMessage) {
            return [
                'status' => 'FAILED',
                'error_message' => $errorMessage ?: $this->faker->sentence(),
                'failed_at' => now(),
            ];
        });
    }
}
