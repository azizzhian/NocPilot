<?php

namespace App\Services\Monitoring;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use RuntimeException;

class RouterMonitor
{
    public function __construct(
        private MikrotikService $mikrotik,
        private SnmpPoller $snmp,
    ) {}

    public function sync(Router $router): bool
    {
        return $this->driver($router)->sync($router);
    }

    public function syncAll(): array
    {
        $results = ['success' => 0, 'failed' => 0];

        Router::where('is_active', true)->each(function (Router $router) use (&$results) {
            $this->sync($router) ? $results['success']++ : $results['failed']++;
        });

        return $results;
    }

    public function syncInterfaces(Router $router): int
    {
        return $this->driver($router)->syncInterfaces($router);
    }

    /**
     * @return array{polled_at: string, traffic: array<int, array{interface_name: string, label: string, rx_bps: int, tx_bps: int, rx_mbps: float, tx_mbps: float, is_running: bool}>}
     */
    public function liveMetrics(Router $router): array
    {
        return $this->driver($router)->liveMetrics($router);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function testConnection(Router $router, array $overrides = []): array
    {
        $via = $overrides['monitor_via'] ?? $router->monitor_via ?? 'api';

        if ($via === 'snmp') {
            return $this->snmp->testConnection(
                $router,
                community: $overrides['snmp_community'] ?? null,
                host: $overrides['ip'] ?? null,
                port: isset($overrides['snmp_port']) ? (int) $overrides['snmp_port'] : null,
            );
        }

        return $this->mikrotik->testConnection(
            $router,
            password: $overrides['password'] ?? null,
            username: $overrides['username'] ?? null,
            host: $overrides['ip'] ?? null,
            port: isset($overrides['api_port']) ? (int) $overrides['api_port'] : null,
        );
    }

    private function driver(Router $router): MikrotikService|SnmpPoller
    {
        if ($router->usesSnmp()) {
            if (! $router->isConfigured()) {
                throw new RuntimeException('Router SNMP belum dikonfigurasi (IP + community).');
            }

            return $this->snmp;
        }

        return $this->mikrotik;
    }
}
