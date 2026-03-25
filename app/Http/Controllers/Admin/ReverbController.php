<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReverbController extends Controller
{
    private string $serviceName = 'woosoo-reverb';
    private string $nssmPath = 'C:\\laragon\\bin\\nssm\\win64\\nssm.exe';

    /**
     * Normalize raw NSSM output into stable UI-safe status values.
     */
    private function normalizeServiceStatus(?string $output): array
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $output)));

        if ($normalized === '') {
            return ['status' => 'unknown', 'message' => 'Service status is unavailable'];
        }

        if (str_contains($normalized, 'SERVICE_RUNNING')) {
            return ['status' => 'running', 'message' => 'Service is running'];
        }

        if (str_contains($normalized, 'SERVICE_STOPPED')) {
            return ['status' => 'stopped', 'message' => 'Service is stopped'];
        }

        if (str_contains($normalized, 'SERVICE_PAUSED')) {
            return ['status' => 'paused', 'message' => 'Service is paused'];
        }

        if (str_contains($normalized, "CAN'T OPEN SERVICE") || str_contains($normalized, 'OPENSERVICE():')) {
            return ['status' => 'not_installed', 'message' => 'Service is not installed'];
        }

        return ['status' => 'unknown', 'message' => 'Service status is unavailable'];
    }

    private function ensureSuperAdmin(string $message = 'Super admin access required'): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        abort_unless($user?->hasRole('super-admin'), 403, $message);
    }

    /**
     * Check if running on Windows
     */
    private function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Get the service status
     */
    private function getStatus(): array
    {
        if (!$this->isWindows()) {
            return ['status' => 'N/A', 'message' => 'Not running on Windows'];
        }

        if (!file_exists($this->nssmPath)) {
            return ['status' => 'error', 'message' => 'Service monitor is unavailable on this host'];
        }

        $output = shell_exec("\"{$this->nssmPath}\" status {$this->serviceName} 2>&1");

        return $this->normalizeServiceStatus($output);
    }

    /**
     * Display Reverb service status
     */
    public function index()
    {
        $this->ensureSuperAdmin();
        
        return Inertia::render('Admin/Reverb', [
            'service' => [
                'name' => $this->serviceName,
                'label' => 'WebSocket Server (Reverb)',
                ...$this->getStatus(),
            ],
            'isWindows' => $this->isWindows(),
            // DO NOT pass sensitive paths to frontend anymore
        ]);
    }

    /**
     * Get status as JSON (for polling)
     */
    public function status()
    {
        $this->ensureSuperAdmin();
        
        return response()->json([
            'service' => $this->serviceName,
            ...$this->getStatus(),
        ]);
    }

    /**
     * Start the Reverb service
     */
    public function start()
    {
        $this->ensureSuperAdmin();
        
        if (!$this->isWindows()) {
            return back()->with('error', 'Not running on Windows');
        }

        shell_exec("\"{$this->nssmPath}\" start {$this->serviceName} 2>&1");
        
        sleep(1);
        
        $status = $this->getStatus();
        
        if ($status['status'] === 'running') {
            return back()->with('success', 'Reverb service started successfully');
        }
        
        return back()->with('error', 'Failed to start service: ' . $status['message']);
    }

    /**
     * Stop the Reverb service
     */
    public function stop()
    {
        $this->ensureSuperAdmin();
        
        if (!$this->isWindows()) {
            return back()->with('error', 'Not running on Windows');
        }

        shell_exec("\"{$this->nssmPath}\" stop {$this->serviceName} 2>&1");
        
        sleep(1);
        
        $status = $this->getStatus();
        
        if ($status['status'] === 'stopped') {
            return back()->with('success', 'Reverb service stopped successfully');
        }
        
        return back()->with('error', 'Failed to stop service: ' . $status['message']);
    }

    /**
     * Restart the Reverb service
     */
    public function restart()
    {
        $this->ensureSuperAdmin();
        
        if (!$this->isWindows()) {
            return back()->with('error', 'Not running on Windows');
        }

        shell_exec("\"{$this->nssmPath}\" restart {$this->serviceName} 2>&1");
        
        sleep(2);
        
        $status = $this->getStatus();
        
        if ($status['status'] === 'running') {
            return back()->with('success', 'Reverb service restarted successfully');
        }
        
        return back()->with('error', 'Failed to restart service: ' . $status['message']);
    }
}
