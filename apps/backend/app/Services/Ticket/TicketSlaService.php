<?php

namespace App\Services\Ticket;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;

class TicketSlaService
{
    private const SLA_HOURS = [
        'critical' => 2,
        'high' => 4,
        'medium' => 8,
        'low' => 24,
    ];

    public function calculateDeadline(string $priority): \DateTimeInterface
    {
        $hours = self::SLA_HOURS[$priority] ?? 8;

        return now()->addHours($hours);
    }

    public function logActivity(
        Ticket $ticket,
        string $action,
        ?string $message = null,
        ?User $user = null,
        ?array $meta = null,
    ): TicketActivity {
        return $ticket->activities()->create([
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    public function formatSlaRemaining(Ticket $ticket): ?string
    {
        $minutes = $ticket->slaRemainingMinutes();

        if ($minutes === null) {
            return null;
        }

        if ($minutes < 0) {
            $abs = abs($minutes);

            return sprintf('-%dj %dm', intdiv($abs, 60), $abs % 60);
        }

        return sprintf('%dj %dm', intdiv($minutes, 60), $minutes % 60);
    }
}
