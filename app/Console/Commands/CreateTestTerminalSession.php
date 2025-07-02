<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Krypton\TerminalSession;
use App\Models\Krypton\Terminal;
use App\Models\Krypton\Session;
use Carbon\Carbon;

class CreateTestTerminalSession extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:test-terminal-session';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $currentDay = $now->toDateString();
        $terminal = Terminal::pos()->first();

        // Get the latest session and today's last session (if needed later)
        $latestSession = Session::latest('date_time_opened')->first();
        $newSession = $latestSession;

        if ($latestSession && $latestSession->date_time_opened->toDateString() !== $currentDay) {
            // Close the old session
            $latestSession->update(['date_time_closed' => $now]);

            // Create a new session
            $newSession = Session::create([
                'date_time_opened' => $now,
            ]);

            // Create a new terminal session
           
        }

        TerminalSession::create([
            'date_time_opened' => $now,
            'terminal_id' => $terminal->id,
            'session_id' => $newSession->id,
            'terminal_session_id'=> $newSession->id,
            'date_time_closed' => null,
            'previous_sale' => 0,
            'current_sale' => 0,
            'accumulated_sale' => 0,
            'transaction_count' => 0,
            'previous_sale_temple' => 0,
            'current_sale_temp' => 0,
            'accumulated_sale_temp' => 0,
        ]);
    }
}
