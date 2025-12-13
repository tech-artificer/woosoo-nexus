<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReverbController extends Controller
{
    private string $serviceName = 'woosoo-reverb';
    private string $nssmPath = 'C:\\laragon\\bin\\nssm\\win64\\nssm.exe';

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
            return ['status' => 'error', 'message' => 'NSSM not found at ' . $this->nssmPath];
        }

        $output = shell_exec("\"{$this->nssmPath}\" status {$this->serviceName} 2>&1");
        $status = trim($output ?? '');

        // Parse NSSM output
        if (str_contains($status, 'SERVICE_RUNNING')) {
            return ['status' => 'running', 'message' => 'Service is running'];
        } elseif (str_contains($status, 'SERVICE_STOPPED')) {
            return ['status' => 'stopped', 'message' => 'Service is stopped'];
        } elseif (str_contains($status, 'SERVICE_PAUSED')) {
            return ['status' => 'paused', 'message' => 'Service is paused'];
        } elseif (str_contains($status, "Can't open service")) {
            return ['status' => 'not_installed', 'message' => 'Service not installed'];
        }

        return ['status' => 'unknown', 'message' => $status];
    }

    /**
     * Display Reverb service status
     */
    public function index()
    {
        return Inertia::render('Admin/Reverb', [
            'service' => [
                'name' => $this->serviceName,
                'label' => 'WebSocket Server (Reverb)',
                ...$this->getStatus(),
            ],
            'isWindows' => $this->isWindows(),
        ]);
    }

    /**
     * Get status as JSON (for polling)
     */
    public function status()
    {
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
