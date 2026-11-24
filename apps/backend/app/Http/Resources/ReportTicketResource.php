<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReportTicket */
class ReportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'complaint_id' => $this->complaint_id,
            'complaint_number' => $this->source_complaint_number,
            'customer_id' => $this->customer_id,
            'location' => $this->location,
            'odc_name' => $this->odc_name,
            'customer_code' => $this->customer_code,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'job_type' => $this->job_type,
            'priority' => $this->priority,
            'visit_schedule' => $this->visit_schedule?->toIso8601String(),
            'source_snapshot' => $this->source_snapshot,
            'problem' => $this->problem,
            'action' => $this->action,
            'status' => $this->status,
            'status_label' => \App\Support\TicketStatus::displayLabel($this->status),
            'opened_at' => $this->opened_at?->toDateString(),
            'closed_at' => $this->closed_at?->toDateString(),
            'notes' => $this->notes,
            'escalation_reason' => $this->escalation_reason,
            'escalated_at' => $this->escalated_at?->toIso8601String(),
            'assigned_to' => $this->assigned_to,
            'assignee_name' => $this->assignee?->name,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'work_started_at' => $this->work_started_at?->toIso8601String(),
            'work_completed_at' => $this->work_completed_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'cleared_by' => $this->cleared_by,
            'clearer_name' => $this->clearer?->name,
            'cleared_at' => $this->cleared_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updates' => $this->whenLoaded('updates', fn () => $this->updates->map(fn ($update) => [
                'id' => $update->id,
                'type' => $update->type,
                'check_result' => $update->check_result,
                'repair_action' => $update->repair_action,
                'material' => $update->material,
                'photo' => $update->photo,
                'work_started_at' => $update->work_started_at?->toIso8601String(),
                'work_ended_at' => $update->work_ended_at?->toIso8601String(),
                'note' => $update->note,
                'author_name' => $update->author?->name,
                'created_at' => $update->created_at?->toIso8601String(),
            ])->all()),
        ];
    }
}
