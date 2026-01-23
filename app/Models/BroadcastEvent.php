<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['channel', 'event', 'payload', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public static function record(string $channel, string $event, array $payload): void
    {
        static::create([
            'channel' => $channel,
            'event' => $event,
            'payload' => json_encode($payload),
            'created_at' => now(),
        ]);

        // Auto-purge events older than 24 hours to prevent table bloat
        static::where('created_at', '<', now()->subHours(24))->delete();
    }
}
