<?php

namespace App\Http\Middleware;

use App\Exceptions\SessionNotFoundException;
use App\Services\Krypton\KryptonContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionIsOpened
{
    public function __construct(private readonly KryptonContextService $kryptonContext) {}

    /**
     * Handle an incoming request.
     *
     * Blocks order-sensitive API routes when no POS session is open.
     * This middleware must only be applied to routes that genuinely require an
     * active POS session (order creation, order updates, refill, service requests).
     * It is NOT applied globally — login, health, config, token, heartbeat, print-bridge,
     * and session endpoints must work regardless of POS session state.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $data = $this->kryptonContext->getData();

            if (empty($data['session_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active POS session. Orders cannot be placed until the POS session is opened.',
                ], 503);
            }
        } catch (SessionNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No active POS session. Orders cannot be placed until the POS session is opened.',
            ], 503);
        }

        return $next($request);
    }
}
