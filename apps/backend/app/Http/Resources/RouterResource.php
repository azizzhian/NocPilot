<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Router */
class RouterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ip' => $this->ip,
            'monitor_via' => $this->monitor_via ?? 'api',
            'api_port' => $this->api_port,
            'username' => $this->username,
            'has_api_password' => filled($this->password),
            'snmp_version' => $this->snmp_version ?? '2c',
            'has_snmp_community' => filled($this->snmp_community),
            'snmp_port' => $this->snmp_port ?? 161,
            'snmp_timeout' => $this->snmp_timeout ?? 3,
            'pop' => $this->pop,
            'area' => $this->area,
            'status' => $this->status,
            'cpu' => $this->cpu,
            'memory' => $this->memory,
            'temperature' => $this->temperature,
            'voltage' => $this->voltage,
            'uptime' => $this->uptime,
            'clients' => $this->clients,
            'pppoe_sessions' => $this->pppoe_sessions,
            'board' => $this->board,
            'version' => $this->version,
            'license' => $this->license,
            'download_mbps' => round($this->download_bps / 1_000_000, 1),
            'upload_mbps' => round($this->upload_bps / 1_000_000, 1),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'sync_error' => $this->sync_error,
            'is_active' => $this->is_active,
            'metrics_source' => $this->metricsSource(),
            'interfaces' => RouterInterfaceResource::collection($this->whenLoaded('interfaces')),
        ];
    }

    private function metricsSource(): string
    {
        if ($this->sync_error) {
            return 'error';
        }

        if ($this->usesSnmp()) {
            return $this->last_synced_at ? 'snmp' : 'pending';
        }

        if (! $this->username) {
            return 'simulated';
        }

        return $this->last_synced_at ? 'api' : 'pending';
    }
}
