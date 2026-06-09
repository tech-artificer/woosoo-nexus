<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Regression guard for nex-case-019 (commit 645a80f): the debug/pos
 * stored-procedure route must NOT leak the raw exception message — it logs
 * the real error and returns a generic 500 instead. Without this test the
 * info-leak protection could silently regress.
 *
 * @see routes/api.php — GET /api/debug/pos/menus/course
 */
it('hides the raw POS exception and logs it when the stored procedure throws', function () {
    // Route is gated behind local env OR app.debug; the test env is "testing".
    config(['app.debug' => true]);

    Log::spy();

    // Force DB::connection('pos')->select(...) to throw with a recognisable
    // secret. A proxied partial mock keeps every other method (transactionLevel,
    // rollBack, ...) delegating to the real sqlite connection so RefreshDatabase
    // and tearDown draining still work.
    $secret = 'SECRET-LEAK SQLSTATE[42000] get_menus_by_course internals';
    $throwingPos = Mockery::mock(DB::connection('pos'))->makePartial();
    $throwingPos->shouldReceive('select')->andThrow(new RuntimeException($secret));

    // Swap the cached 'pos' connection in the DatabaseManager for our throwing one.
    $manager = app('db');
    $connectionsProp = new ReflectionProperty($manager, 'connections');
    $connectionsProp->setAccessible(true);
    $connections = $connectionsProp->getValue($manager);
    $connections['pos'] = $throwingPos;
    $connectionsProp->setValue($manager, $connections);

    $response = $this->getJson('/api/debug/pos/menus/course?course=1');

    $response->assertStatus(500)
        ->assertJson([
            'success' => false,
            'message' => 'POS stored procedure failed. Check Laravel log.',
        ]);

    // The raw exception detail must never reach the client.
    expect($response->getContent())
        ->not->toContain($secret)
        ->not->toContain('SQLSTATE');

    // The real error must still be logged server-side for debugging.
    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context = []) use ($secret) {
            return $message === '[debug/pos] stored procedure failed'
                && ($context['error'] ?? null) === $secret;
        });
});
