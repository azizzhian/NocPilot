<?php

namespace App\Services\Notification;

use App\Jobs\SendN8nWebhookJob;
use App\Models\Activation;
use App\Models\Dismantle;
use App\Models\Ticket;
use App\Services\Realtime\RealtimeService;

class NotificationService
{
    public function __construct(private RealtimeService $realtime) {}
    public function ticketCreated(Ticket $ticket): void
    {
        $payload = [
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'priority' => $ticket->priority,
            'customer_name' => $ticket->customer_name,
            'customer_phone' => $ticket->customer_phone,
            'area' => $ticket->area,
            'sla_deadline' => $ticket->sla_deadline?->toIso8601String(),
        ];

        $this->dispatch('ticket.created', $payload);
        $this->realtime->push(
            'ticket.created',
            "Ticket baru {$ticket->ticket_number}",
            $ticket->subject,
            $ticket->priority === 'critical' ? 'critical' : 'warning',
            $payload,
        );
    }

    public function ticketStatusChanged(Ticket $ticket, string $oldStatus): void
    {
        $this->dispatch('ticket.status_changed', [
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'old_status' => $oldStatus,
            'new_status' => $ticket->status,
            'priority' => $ticket->priority,
            'customer_name' => $ticket->customer_name,
        ]);
    }

    public function ticketAssigned(Ticket $ticket, string $assigneeName): void
    {
        $this->dispatch('ticket.assigned', [
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'assignee' => $assigneeName,
            'customer_name' => $ticket->customer_name,
            'area' => $ticket->area,
        ]);
    }

    public function activationCreated(Activation $activation): void
    {
        $this->dispatch('activation.created', [
            'reference' => $activation->reference,
            'customer_name' => $activation->customer_name,
            'package' => $activation->package,
            'area' => $activation->area,
            'scheduled_at' => $activation->scheduled_at?->toIso8601String(),
        ]);
    }

    public function dismantleCreated(Dismantle $dismantle): void
    {
        $this->dispatch('dismantle.created', [
            'reference' => $dismantle->reference,
            'customer_name' => $dismantle->customer_name,
            'customer_code' => $dismantle->customer_code,
            'location' => $dismantle->location,
            'status' => $dismantle->status,
        ]);
    }

    protected function dispatch(string $event, array $payload): void
    {
        SendN8nWebhookJob::dispatch($event, $payload);
    }
}
