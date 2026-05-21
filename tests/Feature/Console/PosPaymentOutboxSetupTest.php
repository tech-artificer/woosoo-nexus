<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PosPaymentOutboxSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_command_creates_pos_local_outbox_without_requiring_same_mysql_endpoint(): void
    {
        config([
            'database.connections.pos.host' => '192.168.1.32',
            'database.connections.pos.port' => '3306',
            'database.connections.mysql.host' => 'mysql',
            'database.connections.mysql.port' => '3306',
        ]);

        Schema::connection('pos')->dropIfExists('woosoo_order_status_outbox');
        Schema::connection('pos')->dropIfExists('woosoo_session_status_outbox');

        $this->artisan('pos:setup-payment-trigger')
            ->assertExitCode(0);

        $this->assertTrue(Schema::connection('pos')->hasTable('woosoo_order_status_outbox'));
        $this->assertTrue(Schema::connection('pos')->hasTable('woosoo_session_status_outbox'));
    }
}
