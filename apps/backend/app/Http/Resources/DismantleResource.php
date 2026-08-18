<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Dismantle */
class DismantleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'location' => $this->location,
            'customer_code' => $this->customer_code,
            'phone' => $this->phone,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toDateString(),
            'closed_at' => $this->closed_at?->toDateString(),
            'notes' => $this->notes,
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->name,
            ]),
            'creator_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
