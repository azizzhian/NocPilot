<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_code' => $this->customer_code,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'odc_id' => $this->odc_id,
            'odc' => $this->whenLoaded('odc', fn () => $this->odc ? [
                'id' => $this->odc->id,
                'name' => $this->odc->name,
                'code' => $this->odc->code,
            ] : null),
            'status' => $this->status,
            'is_active' => $this->status === 'active',
            'pppoe' => $this->pppoe,
            'email' => $this->email,
            'package' => $this->package,
            'area' => $this->area,
            'imported_at' => $this->imported_at?->toIso8601String(),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
