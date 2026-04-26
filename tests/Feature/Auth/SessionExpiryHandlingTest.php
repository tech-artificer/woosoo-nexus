<?php

use App\Exceptions\Handler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

uses(RefreshDatabase::class);

test('token mismatch redirects back with a warning for web requests', function () {
    $request = Request::create('/login', 'POST', [], [], [], [
        'HTTP_REFERER' => 'https://192.168.100.7/login',
    ]);

    $session = $this->app['session']->driver();
    $session->start();
    $request->setLaravelSession($session);

    $response = app(Handler::class)->render($request, new TokenMismatchException());

    expect($response->getStatusCode())->toBe(302);
        expect($response->headers->get('Location'))->toBe(url('/'));
    expect($session->get('warning'))->toBe('Your session expired. Please sign in again.');
});

test('the login page shows the session-expired warning banner', function () {
    $this->withoutVite();

    $this->withSession(['warning' => 'Your session expired. Please sign in again.'])
        ->get('/login')
        ->assertOk()
            ->assertSee('&quot;warning&quot;:&quot;Your session expired. Please sign in again.&quot;', false)
            ->assertSee('&quot;canResetPassword&quot;:true', false);
});
