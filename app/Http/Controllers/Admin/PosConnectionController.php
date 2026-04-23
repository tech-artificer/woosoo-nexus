<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePosConnectionRequest;
use App\Models\SystemSetting;
use App\Services\PosConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PosConnectionController extends Controller
{
    public function __construct(private readonly PosConnectionService $posService) {}

    public function index()
    {
        $creds = SystemSetting::hasPosConnection()
            ? SystemSetting::getPosConnection()
            : null;

        return Inertia::render('configuration/PosConnection', [
            'connection' => $creds ? [
                'host'     => $creds['host'],
                'port'     => $creds['port'],
                'database' => $creds['database'],
                'username' => $creds['username'],
                // Never expose the stored password to the frontend.
                'has_password' => $creds['password'] !== null,
            ] : null,
        ]);
    }

    public function update(UpdatePosConnectionRequest $request)
    {
        $data = $request->validated();

        SystemSetting::set('pos.host', $data['host']);
        SystemSetting::set('pos.port', (string) $data['port']);
        SystemSetting::set('pos.database', $data['database']);
        SystemSetting::set('pos.username', $data['username']);

        // Only update password if a new one was provided.
        if (filled($data['password'])) {
            SystemSetting::set('pos.password', $data['password'], 'encrypted');
        }

        // Force-apply new config and purge the active connection,
        // then invalidate the Krypton context cache so the next request
        // picks up a fresh session from the new POS database.
        DB::purge('pos');
        $this->posService->applyFromDatabase();
        Cache::forget('krypton.context');

        return redirect()->back()->with('success', 'POS connection settings saved.');
    }

    /**
     * Test provided credentials without saving. Returns JSON.
     */
    public function test(Request $request)
    {
        $validated = $request->validate([
            'host'     => ['required', 'string', 'max:253'],
            'port'     => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:80'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        // If password is blank, use the currently stored password (if any).
        $password = filled($validated['password'])
            ? $validated['password']
            : (SystemSetting::get('pos.password') ?? '');

        $result = $this->posService->testCredentials(
            $validated['host'],
            (string) $validated['port'],
            $validated['database'],
            $validated['username'],
            $password
        );

        return response()->json($result);
    }
}
