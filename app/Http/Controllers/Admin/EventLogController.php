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
        $lines = [];

        if (file_exists($path)) {
            // read last N lines without loading entire file
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
                    // start of file
                    $rows[] = strrev($currentLine);
                    break;
                }
            }
            fclose($fp);

            $lines = array_reverse(array_filter($rows));
        }

        return Inertia::render('EventLogs/Index', [
            'title' => 'Event Logs',
            'description' => 'Recent application log lines',
            'logs' => $lines,
        ]);
    }
}
