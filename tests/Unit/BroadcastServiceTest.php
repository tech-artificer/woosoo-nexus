<?php

namespace Tests\Unit;

use App\Events\AppControlEvent;
use App\Services\BroadcastService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BroadcastServiceTest extends TestCase
{
    public function test_dispatch_broadcast_job_broadcasts_synchronously_without_queueing(): void
    {
        Queue::fake();

        $event = new AppControlEvent(7, 'reload', ['reason' => 'test']);

        $service = $this->getMockBuilder(BroadcastService::class)
            ->onlyMethods(['broadcastWithRetry'])
            ->getMock();

        $service->expects($this->once())
            ->method('broadcastWithRetry')
            ->with($event)
            ->willReturn(true);

        $service->dispatchBroadcastJob($event);

        Queue::assertNothingPushed();
    }
}