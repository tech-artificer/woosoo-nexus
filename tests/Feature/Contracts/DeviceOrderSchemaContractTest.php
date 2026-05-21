<?php

namespace Tests\Feature\Contracts;

use Tests\TestCase;

/**
 * Schema contract: device_orders does NOT have a `print_event_id` column.
 *
 * The relationship between DeviceOrder and PrintEvent is owned by the
 * PRINT_EVENTS side via `print_events.device_order_id` (foreign key).
 * DeviceOrder exposes this via the `printEvent()` / `printEvents()`
 * Eloquent relations.
 *
 * Writing `$deviceOrder->print_event_id = …; $deviceOrder->save();`
 * generates `UPDATE device_orders SET print_event_id = …` which throws
 * `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'print_event_id'`
 * against the production MySQL schema. SQLite (test env) tolerates the
 * unknown attribute silently, so functional tests don't catch this —
 * we lock the contract here at the source-code level.
 *
 * If a future feature legitimately needs `device_orders.print_event_id`,
 * (1) ship the migration first, (2) delete this test in the same commit.
 */
class DeviceOrderSchemaContractTest extends TestCase
{
    /**
     * Production code must never assign `print_event_id` directly on a
     * DeviceOrder instance and persist it — the column doesn't exist.
     */
    public function test_no_code_writes_device_orders_print_event_id_column(): void
    {
        $files = [
            app_path('Services/Krypton/OrderService.php'),
            app_path('Http/Controllers/Api/V1/OrderApiController.php'),
            app_path('Http/Controllers/Api/V1/DeviceOrderApiController.php'),
        ];

        foreach ($files as $path) {
            $this->assertFileExists($path, "Expected source file missing: {$path}");

            $contents = file_get_contents($path);

            // The specific anti-pattern that caused the production outage:
            //   $deviceOrder->print_event_id = $printEvent->id;
            // Any future assignment to that property on a DeviceOrder is
            // suspect — assert the literal string is absent.
            $this->assertStringNotContainsString(
                '->print_event_id = $printEvent',
                $contents,
                sprintf(
                    "%s writes to device_orders.print_event_id, which does not exist. "
                    . "Use the DeviceOrder::printEvent() relation instead, or ship a migration first.",
                    basename($path)
                )
            );

            // Belt and suspenders: catch raw DB::table updates too.
            $this->assertStringNotContainsString(
                "update('device_orders'",
                $contents,
                sprintf("%s contains a raw DB::table('device_orders')->update call — verify it does not set print_event_id.", basename($path))
            );
        }
    }
}
