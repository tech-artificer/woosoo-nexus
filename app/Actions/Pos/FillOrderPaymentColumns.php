<?php

namespace App\Actions\Pos;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Simple helper Action to modify POS `orders` rows for testing triggers.
 * Usage:
 * \App\Actions\Pos\FillOrderPaymentColumns::run(42, [
 *   'date_time_closed' => '2025-12-05 12:00:00',
 *   'is_open' => 0,
 *   'is_voided' => 0,
 *   'session_id' => 123,
 * ]);
 */
class FillOrderPaymentColumns
{
    /**
     * Update a POS orders row with the provided attributes.
     * Allowed attributes: date_time_closed, is_open, is_voided, session_id
     *
     * Returns the updated row (stdClass) from the `pos` connection.
     *
     * @param int $orderId
     * @param array $attrs
     * @return object|null
     */
    public static function run(int $orderId, array $attrs = [])
    {
        $allowed = ['date_time_closed', 'is_open', 'is_voided', 'session_id'];

        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $attrs)) {
                $value = $attrs[$key];
                if ($key === 'date_time_closed' && $value !== null) {
                    $value = Carbon::parse($value)->toDateTimeString();
                }
                // Skip session_id if it doesn't exist in the sessions table to avoid FK violation
                if ($key === 'session_id' && $value !== null) {
                    $exists = DB::connection('pos')->table('sessions')->where('id', $value)->exists();
                    if (!$exists) {
                        continue; // Skip this field - keep original session_id
                    }
                }
                $data[$key] = $value;
            }
        }

        if (empty($data)) {
            throw new \InvalidArgumentException('No valid attributes provided. Allowed: ' . implode(', ', $allowed));
        }

        DB::connection('pos')->table('orders')->where('id', $orderId)->update($data);

        return DB::connection('pos')->table('orders')->where('id', $orderId)->first();
    }
}
