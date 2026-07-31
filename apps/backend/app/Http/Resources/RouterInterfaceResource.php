<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RouterInterface */
class RouterInterfaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'router_id' => $this->router_id,
            'interface_name' => $this->interface_name,
            'label' => $this->label,
            'display_name' => $this->displayName(),
            'is_monitored' => $this->is_monitored,
            'is_running' => $this->is_running,
            'rx_bps' => (int) ($this->rx_bps ?? 0),
            'tx_bps' => (int) ($this->tx_bps ?? 0),
            'rx_mbps' => round(((int) ($this->rx_bps ?? 0)) / 1_000_000, 2),
            'tx_mbps' => round(((int) ($this->tx_bps ?? 0)) / 1_000_000, 2),
            'traffic_polled_at' => $this->traffic_polled_at?->toIso8601String(),
        ];
    }
}
