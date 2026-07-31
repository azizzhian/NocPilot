<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RealtimeEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyEntryEventsController extends Controller
{
    private const CHANNEL = 'daily-entry';

    public function index(Request $request): JsonResponse
    {
        $date = $request->string('date', now()->toDateString())->toString();
        $since = $request->has('since')
            ? max(0, $request->integer('since'))
            : (int) (RealtimeEvent::query()
                ->where('channel', self::CHANNEL)
                ->where('payload->report_date', $date)
                ->max('id') ?? 0);

        $events = RealtimeEvent::query()
            ->where('channel', self::CHANNEL)
            ->where('id', '>', $since)
            ->where('payload->report_date', $date)
            ->orderBy('id')
            ->limit(50)
            ->get();

        $latestId = (int) (RealtimeEvent::query()
            ->where('channel', self::CHANNEL)
            ->where('payload->report_date', $date)
            ->max('id') ?? $since);

        return response()->json([
            'events' => $events->map(fn (RealtimeEvent $event) => [
                'id' => $event->id,
                'event' => $event->event,
                'title' => $event->title,
                'message' => $event->message,
                'payload' => $event->payload,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values(),
            'latest_id' => max($latestId, $since),
        ]);
    }
}
