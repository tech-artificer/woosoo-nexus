<?php

namespace App\Http\Controllers\Api;

use App\Models\BroadcastEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventReplayController
{
    /**
     * Get missed broadcast events since timestamp
     * Used by tablet PWA to recover from brief disconnections
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function missing(Request $request): JsonResponse
    {
        $since = $request->query('since'); // ISO8601 timestamp
        $channel = $request->query('channel'); // e.g., 'admin.print'

        if (!$since || !$channel) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required query params: since, channel',
            ], 400);
        }

        try {
            $since = \Carbon\Carbon::parse($since);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid since timestamp: ' . $e->getMessage(),
            ], 400);
        }

        $events = BroadcastEvent::where('channel', $channel)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (BroadcastEvent $event) {
                return [
                    'id' => $event->id,
                    'event' => $event->event,
                    'payload' => json_decode($event->payload, true),
                    'timestamp' => $event->created_at->iso8601(),
                ];
            });

        return response()->json([
            'success' => true,
            'channel' => $channel,
            'since' => $since->iso8601(),
            'count' => $events->count(),
            'events' => $events,
        ]);
    }
}
