<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RealtimeEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $since = $request->integer('since', 0);

        $query = RealtimeEvent::query()
            ->where('channel', 'noc')
            ->latest()
            ->limit(50);

        if ($since > 0) {
            $query->where('id', '>', $since);
        }

        $events = $query->get()->reverse()->values();

        return response()->json([
            'events' => $events->map(fn ($e) => [
                'id' => $e->id,
                'event' => $e->event,
                'title' => $e->title,
                'message' => $e->message,
                'severity' => $e->severity,
                'payload' => $e->payload,
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
            'latest_id' => RealtimeEvent::max('id') ?? 0,
            'unread' => RealtimeEvent::whereNull('read_at')->count(),
        ]);
    }

    public function markRead(): JsonResponse
    {
        RealtimeEvent::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifikasi ditandai dibaca.']);
    }
}
