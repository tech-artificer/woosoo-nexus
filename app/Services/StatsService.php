<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class StatsService
{
    /**
     * Compute a sparkline array of daily counts for the past $days days for a given Eloquent model class.
     * This method guards for tables that do not expose a `created_at` timestamp (e.g. 3rd-party tables)
     * and will try fallback column names like `created_on`.
     *
     * @param string $modelClass Fully-qualified model class name (e.g. App\\Models\\Krypton\\Menu)
     * @param int $days Number of days to include (default 7)
     * @param array $candidates Column names to try in order
     * @return array Numeric array with $days integers representing daily counts (oldest -> newest)
     */
    public static function sparklineForModel(string $modelClass, int $days = 7, array $candidates = ['created_at', 'created_on', 'created']) : array
    {
        // validate model class
        if (!class_exists($modelClass)) {
            return array_fill(0, $days, 0);
        }

        /** @var Model $instance */
        $instance = new $modelClass();
        $table = $instance->getTable();
        $connName = $instance->getConnectionName() ?: config('database.default');

        // find a usable datetime column
        $column = null;
        foreach ($candidates as $c) {
            try {
                if (Schema::connection($connName)->hasColumn($table, $c)) {
                    $column = $c;
                    break;
                }
            } catch (\Throwable $e) {
                // If schema inspection fails, stop trying to avoid exceptions bubbling up
                $column = null;
                break;
            }
        }

        if (!$column) {
            return array_fill(0, $days, 0);
        }

        $today = Carbon::today();
        $start = $today->copy()->subDays($days - 1)->startOfDay();

        try {
            $daily = DB::connection($connName)->table($table)
                ->where($column, '>=', $start)
                ->selectRaw("DATE($column) as date, COUNT(*) as cnt")
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('cnt', 'date')
                ->toArray();
        } catch (\Throwable $e) {
            return array_fill(0, $days, 0);
        }

        $spark = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i)->toDateString();
            $spark[] = isset($daily[$d]) ? (int) $daily[$d] : 0;
        }

        return $spark;
    }
}
