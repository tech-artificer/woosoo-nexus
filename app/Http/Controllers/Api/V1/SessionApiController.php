<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Krypton\Session as KryptonSession;
use App\Events\SessionReset;

class SessionApiController extends Controller
{
    /**
     * Return current active session for a branch.
     * 
     * Response shape: the session object at root level (or null when none active).
     * Clients access `response.data.id` via Axios — do NOT nest under a `session` key.
     */
    public function current(Request $request)
    {
        try {
            $session = KryptonSession::getLatestSession();
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch latest session: ' . $e->getMessage());
            $session = null;
        }

        // Return session at root so Axios clients can read `data.id` directly.
        return response()->json($session);
    }

    /**
     * Return the latest active session wrapped under a `session` key.
     *
     * Called by the print-bridge (GET /api/devices/latest-session).
     * Distinct from current() which returns the session at root level for Axios clients.
     *
     * Response shape: { session: { id, ... } } or { session: null }
     *
     * @unauthenticated
     */
    public function latestSession(Request $request)
    {
        try {
            $session = KryptonSession::getLatestSession();
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch latest session (latestSession): ' . $e->getMessage());
            $session = null;
        }

        return response()->json(['session' => $session]);
    }

    /**
     * Return session metadata.
     */
    public function show(Request $request, int $id)
    {
        $s = KryptonSession::find($id);
        if (! $s) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        $isActive = (isset($s->status) && strtoupper($s->status) === 'ACTIVE') || (isset($s->date_time_closed) && $s->date_time_closed === null);

        return response()->json(['success' => true, 'session' => $s, 'is_active' => (bool)$isActive]);
    }

    /**
     * Reset a session: clear server caches and broadcast a session.reset event so clients can clear local caches.
     * Requires `auth:sanctum` and administrative access or device.
     */
    public function reset(Request $request, int $id)
    {
        $user = $request->user();
        // Allow admins (User->is_admin) or devices
        $isAdmin = isset($user->is_admin) && $user->is_admin;
        $isDevice = $user && get_class($user) === '\\App\\Models\\Device';

        if (! $isAdmin && ! $isDevice) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // bump session version in cache
        $versionKey = "session:{$id}:version";
        if (! Cache::has($versionKey)) {
            Cache::put($versionKey, 1);
            $version = 1;
        } else {
            $version = Cache::increment($versionKey);
        }

        // broadcast reset event
        try {
            SessionReset::dispatch($id, $version);
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch SessionReset: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Session reset dispatched', 'version' => $version]);
    }
}
