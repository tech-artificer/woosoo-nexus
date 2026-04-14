<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventLogController extends Controller
{
    public function index(Request $request)
    {
        $path = storage_path('logs/laravel.log');
        $sanitizedLogs = [];

        if (file_exists($path)) {
            // Read last 500 lines
            $N = 500;
            $fp = fopen($path, 'r');
            $pos = -1;
            $currentLine = '';
            $rows = [];

            fseek($fp, 0, SEEK_END);
            $fileSize = ftell($fp);

            while (count($rows) < $N && $fileSize > 0) {
                $pos--;
                fseek($fp, $pos, SEEK_END);
                $char = fgetc($fp);
                if ($char === "\n") {
                    $rows[] = strrev($currentLine);
                    $currentLine = '';
                } else {
                    $currentLine .= $char;
                }
                if (ftell($fp) + $pos <= 0) {
                    $rows[] = strrev($currentLine);
                    break;
                }
            }
            fclose($fp);

            $lines = array_reverse(array_filter($rows));

            // Sanitize each log line
            $isSuperAdmin = auth()->user()?->hasRole('super-admin') ?? false;

            foreach ($lines as $line) {
                // Extract log level and timestamp if present
                preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(\w+\.\w+):\s+(.*)$/', $line, $matches);
                
                if ($matches) {
                    [$full, $timestamp, $level, $message] = $matches;
                    
                    // Truncate message to 200 chars unless super-admin
                    $displayMessage = $isSuperAdmin ? $message : \Illuminate\Support\Str::limit($message, 200);
                    
                    // Scrub sensitive patterns even for super-admin raw view
                    $displayMessage = $this->sanitizeLogMessage($displayMessage);
                    
                    $sanitizedLogs[] = [
                        'timestamp' => $timestamp,
                        'level' => $level,
                        'message' => $displayMessage,
                        'raw' => $isSuperAdmin ? $line : null, // Full raw line only for super-admin
                    ];
                } else {
                    // Non-standard format, sanitize and truncate
                    $sanitized = $this->sanitizeLogMessage($line);
                    $sanitizedLogs[] = [
                        'timestamp' => null,
                        'level' => 'info',
                        'message' => $isSuperAdmin ? $sanitized : \Illuminate\Support\Str::limit($sanitized, 200),
                        'raw' => $isSuperAdmin ? $line : null,
                    ];
                }
            }
        }

        return Inertia::render('EventLogs/Index', [
            'title' => 'Event Logs',
            'description' => 'Recent application log entries (sanitized)',
            'logs' => $sanitizedLogs,
            'isSuperAdmin' => auth()->user()?->hasRole('super-admin') ?? false,
        ]);
    }

    /**
     * Sanitize log message - remove sensitive patterns
     */
    private function sanitizeLogMessage(string $message): string
    {
        // Remove filesystem paths
        $message = preg_replace('#[A-Z]:[\\\/][^"\s]*#i', '[PATH_REDACTED]', $message);
        $message = preg_replace('#/var/www/[^"\s]*#i', '[PATH_REDACTED]', $message);
        $message = preg_replace('#/home/[^"\s]*#i', '[PATH_REDACTED]', $message);
        
        // Remove MySQL connection strings
        $message = preg_replace('#mysql:host=[^;"\s]+(;[^"\s]*)?#i', '[DB_CONNECTION_REDACTED]', $message);
        
        // Remove potential credentials
        $message = preg_replace('#(password|secret|token|key)[\s]*[=:][\s]*[^\s]+#i', '$1=[REDACTED]', $message);
        
        return $message;
    }
}
