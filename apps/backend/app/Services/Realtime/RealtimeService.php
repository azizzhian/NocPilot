<?php

namespace App\Services\Realtime;

use App\Models\RealtimeEvent;

class RealtimeService
{
    public function push(
        string $event,
        string $title,
        ?string $message = null,
        string $severity = 'info',
        ?array $payload = null,
        string $channel = 'noc',
    ): RealtimeEvent {
        return RealtimeEvent::create([
            'event' => $event,
            'channel' => $channel,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'payload' => $payload,
        ]);
    }
}
