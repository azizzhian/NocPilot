<?php

namespace App\Http\Resources;

use App\Services\Ticket\TicketSlaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Ticket */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sla = app(TicketSlaService::class);

        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'description' => $this->description,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'priority' => $this->priority,
            'status' => $this->status,
            'area' => $this->area,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'sla_deadline' => $this->sla_deadline?->toIso8601String(),
            'sla_remaining' => $sla->formatSlaRemaining($this->resource),
            'sla_breached' => $this->sla_deadline && $this->sla_deadline->isPast() && ! in_array($this->status, ['solved', 'closed']),
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->name,
            ]),
            'internal_note' => $this->when($request->user()?->can('ticket.manage'), $this->internal_note),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'solved_at' => $this->solved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'activities' => TicketActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
