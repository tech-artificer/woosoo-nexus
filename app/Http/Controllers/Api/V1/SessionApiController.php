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
     */
    public function current(Request $request)
    {
        $branchId = $request->query('branch_id') ?? ($request->user()?->branch_id ?? null);

        try {
            $session = KryptonSession::getLatestSession();
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch latest session: ' . $e->getMessage());
            $session = null;
        }

        if (! $session) {
            return response()->json(['success' => true, 'session' => null]);
        }

        return response()->json(['success' => true, 'session' => $session]);
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
        $user = $request()->user();
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
