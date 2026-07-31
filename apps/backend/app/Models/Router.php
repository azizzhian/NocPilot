<?php

namespace App\Models;

use Database\Factories\RouterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'ip', 'monitor_via', 'api_port', 'username', 'password',
    'snmp_version', 'snmp_community', 'snmp_port', 'snmp_timeout',
    'pop', 'area',
    'status', 'cpu', 'memory', 'temperature', 'voltage', 'uptime',
    'clients', 'pppoe_sessions', 'board', 'version', 'license',
    'download_bps', 'upload_bps', 'last_synced_at', 'sync_error', 'is_active',
])]
#[Hidden(['password', 'snmp_community'])]
class Router extends Model
{
    /** @use HasFactory<RouterFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'password' => 'encrypted',
            'snmp_community' => 'encrypted',
            'snmp_port' => 'integer',
            'snmp_timeout' => 'integer',
        ];
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function usesSnmp(): bool
    {
        return ($this->monitor_via ?? 'api') === 'snmp';
    }

    public function interfaces(): HasMany
    {
        return $this->hasMany(RouterInterface::class);
    }

    public function monitoredInterfaces(): HasMany
    {
        return $this->hasMany(RouterInterface::class)->where('is_monitored', true);
    }

    public function isConfigured(): bool
    {
        if (! filled($this->ip)) {
            return false;
        }

        if ($this->usesSnmp()) {
            return filled($this->snmp_community);
        }

        return filled($this->username) && filled($this->password);
    }
}
