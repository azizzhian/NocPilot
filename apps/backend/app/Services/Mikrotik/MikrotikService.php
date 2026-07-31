<?php

namespace App\Services\Mikrotik;

use App\Models\Router;
use App\Models\RouterInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MikrotikService
{
    public function sync(Router $router): bool
    {
        try {
            if ($router->username && $router->password) {
                $metrics = $this->fetchFromApi($router);
            } else {
                $metrics = $this->simulateMetrics($router);
            }

            $router->update([
                ...$metrics,
                'last_synced_at' => now(),
                'sync_error' => null,
            ]);

            if ($router->interfaces()->count() === 0) {
                try {
                    $this->syncInterfaces($router->fresh());
                } catch (Throwable $e) {
                    Log::warning("Interface sync skipped for {$router->name}: {$e->getMessage()}");
                }
            }

            try {
                $this->persistMonitoredTraffic($router->fresh());
            } catch (Throwable $e) {
                Log::warning("API traffic persist skipped for {$router->name}: {$e->getMessage()}");
            }

            return true;
        } catch (Throwable $e) {
            Log::warning("MikroTik sync failed for {$router->name}: {$e->getMessage()}");

            $router->update([
                'status' => 'offline',
                'sync_error' => $this->friendlyError($e),
                'last_synced_at' => now(),
            ]);

            return false;
        }
    }

    public function syncAll(): array
    {
        $results = ['success' => 0, 'failed' => 0];

        Router::where('is_active', true)->each(function (Router $router) use (&$results) {
            $this->sync($router) ? $results['success']++ : $results['failed']++;
        });

        return $results;
    }

    /**
     * @return array{success: bool, message: string, latency_ms: int, identity: string|null, host: string, port: int, username: string, username_source: string, password_source: string, password_length: int}
     */
    public function testConnection(
        Router $router,
        ?string $password = null,
        ?string $username = null,
        ?string $host = null,
        ?int $port = null,
    ): array {
        $usernameFromInput = $username !== null && $username !== '';
        $passwordFromInput = $password !== null && $password !== '';
        $username = $usernameFromInput ? $username : $router->username;
        $password = $passwordFromInput ? $password : $router->password;
        $host = $host ?: $router->ip;
        $port = $port ?: ($router->api_port ?? 8728);

        if (! $username || ! $password) {
            throw new RuntimeException('Username dan password API wajib diisi.');
        }

        $started = microtime(true);
        $client = $this->connectClient($host, $port, $username, $password);

        try {
            $identity = $this->firstReRecord($client->query('/system/identity/print'))['name'] ?? null;

            return [
                'success' => true,
                'message' => 'Koneksi API MikroTik berhasil.',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'identity' => $identity,
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'username_source' => $usernameFromInput ? 'input' : 'stored',
                'password_source' => $passwordFromInput ? 'input' : 'stored',
                'password_length' => strlen($password),
            ];
        } finally {
            $client->disconnect();
        }
    }

    protected function fetchFromApi(Router $router): array
    {
        $client = $this->connectClient(
            $router->ip,
            $router->api_port ?? 8728,
            $router->username,
            $router->password,
        );

        try {
            $resource = $this->firstReRecord($client->query('/system/resource/print'));
            $license = $this->firstReRecord($client->query('/system/license/print'));
            $pppActive = $this->reRecords($client->query('/ppp/active/print'));

            $temperature = 0;
            $voltage = null;

            try {
                foreach ($this->reRecords($client->query('/system/health/print')) as $sensor) {
                    $name = strtolower($sensor['name'] ?? '');
                    if (str_contains($name, 'temperature')) {
                        $temperature = (int) round((float) ($sensor['value'] ?? 0));
                    }
                    if (str_contains($name, 'voltage')) {
                        $voltage = (int) round((float) ($sensor['value'] ?? 0));
                    }
                }
            } catch (Throwable) {
                // Not all boards expose health sensors.
            }

            $totalMemory = (int) ($resource['total-memory'] ?? 0);
            $freeMemory = (int) ($resource['free-memory'] ?? 0);
            $memoryPercent = $totalMemory > 0
                ? (int) round((($totalMemory - $freeMemory) / $totalMemory) * 100)
                : 0;

            $pppoeCount = count($pppActive);

            $wirelessCount = 0;
            try {
                $wirelessCount = count($this->reRecords($client->query('/interface/wireless/registration-table/print')));
            } catch (Throwable) {
                // No wireless package or interface.
            }

            [$downloadBps, $uploadBps] = [0, 0];
            try {
                $router->load('monitoredInterfaces');
                [$downloadBps, $uploadBps] = $this->fetchTrafficRates($client, $router);
            } catch (Throwable) {
                // Beberapa router menutup koneksi saat monitor-traffic — metrik lain tetap disimpan.
            }

            return [
                'status' => 'online',
                'cpu' => min(100, (int) ($resource['cpu-load'] ?? 0)),
                'memory' => min(100, $memoryPercent),
                'temperature' => $temperature,
                'voltage' => $voltage,
                'uptime' => $resource['uptime'] ?? '-',
                'clients' => $pppoeCount + $wirelessCount,
                'pppoe_sessions' => $pppoeCount,
                'board' => $resource['board-name'] ?? null,
                'version' => $resource['version'] ?? null,
                'license' => isset($license['nlevel'])
                    ? 'Level '.$license['nlevel']
                    : ($license['level'] ?? null),
                'download_bps' => $downloadBps,
                'upload_bps' => $uploadBps,
            ];
        } finally {
            $client->disconnect();
        }
    }

    /**
     * @return array<int, array{name: string, type: string, running: bool, disabled: bool}>
     */
    public function fetchInterfaces(Router $router): array
    {
        if (! $router->isConfigured()) {
            throw new RuntimeException('Router belum dikonfigurasi (IP/user/password).');
        }

        $client = $this->connectClient(
            $router->ip,
            $router->api_port ?? 8728,
            $router->username,
            $router->password,
        );

        try {
            return collect($this->reRecords($client->query('/interface/print')))
                ->map(fn (array $row) => [
                    'name' => $row['name'] ?? '',
                    'type' => $row['type'] ?? '',
                    'running' => ($row['running'] ?? '') === 'true',
                    'disabled' => ($row['disabled'] ?? '') === 'true',
                ])
                ->filter(fn (array $row) => $row['name'] !== '')
                ->sortBy('name')
                ->values()
                ->all();
        } finally {
            $client->disconnect();
        }
    }

    public function syncInterfaces(Router $router): int
    {
        $remote = $this->fetchInterfaces($router);
        $count = 0;
        $remoteNames = [];

        foreach ($remote as $iface) {
            $remoteNames[] = $iface['name'];
            RouterInterface::updateOrCreate(
                [
                    'router_id' => $router->id,
                    'interface_name' => $iface['name'],
                ],
                [
                    'label' => $iface['name'],
                    'is_running' => $iface['running'] && ! $iface['disabled'],
                ]
            );
            $count++;
        }

        $router->interfaces()
            ->whereNotIn('interface_name', $remoteNames)
            ->delete();

        return $count;
    }

    /**
     * @return array{polled_at: string, traffic: array<int, array{interface_name: string, label: string, rx_bps: int, tx_bps: int, rx_mbps: float, tx_mbps: float, is_running: bool}>}
     */
    public function liveMetrics(Router $router): array
    {
        $router->load(['monitoredInterfaces']);

        $fromDb = $router->monitoredInterfaces->map(fn (RouterInterface $iface) => [
            'interface_name' => $iface->interface_name,
            'label' => $iface->displayName(),
            'rx_bps' => (int) ($iface->rx_bps ?? 0),
            'tx_bps' => (int) ($iface->tx_bps ?? 0),
            'rx_mbps' => round(((int) ($iface->rx_bps ?? 0)) / 1_000_000, 2),
            'tx_mbps' => round(((int) ($iface->tx_bps ?? 0)) / 1_000_000, 2),
            'is_running' => (bool) $iface->is_running,
            'traffic_polled_at' => $iface->traffic_polled_at?->toIso8601String(),
        ])->values()->all();

        if ($fromDb !== []) {
            return [
                'polled_at' => now()->toIso8601String(),
                'source' => 'collector',
                'traffic' => $fromDb,
            ];
        }

        if (! $router->isConfigured()) {
            throw new RuntimeException('Router belum dikonfigurasi (IP/user/password).');
        }

        $client = $this->connectClient(
            $router->ip,
            $router->api_port ?? 8728,
            $router->username,
            $router->password,
        );

        try {
            $traffic = [];

            foreach ($router->monitoredInterfaces as $iface) {
                [$rx, $tx] = $this->getInterfaceTraffic($client, $iface->interface_name);
                $traffic[] = [
                    'interface_name' => $iface->interface_name,
                    'label' => $iface->displayName(),
                    'rx_bps' => $rx,
                    'tx_bps' => $tx,
                    'rx_mbps' => round($rx / 1_000_000, 2),
                    'tx_mbps' => round($tx / 1_000_000, 2),
                    'is_running' => $iface->is_running,
                ];
            }

            return [
                'polled_at' => now()->toIso8601String(),
                'source' => 'api',
                'traffic' => $traffic,
            ];
        } finally {
            $client->disconnect();
        }
    }

    public function persistMonitoredTraffic(Router $router): void
    {
        if (! $router->isConfigured()) {
            return;
        }

        $router->loadMissing('monitoredInterfaces');
        if ($router->monitoredInterfaces->isEmpty()) {
            return;
        }

        $client = $this->connectClient(
            $router->ip,
            $router->api_port ?? 8728,
            $router->username,
            $router->password,
        );

        try {
            $now = now();
            foreach ($router->monitoredInterfaces as $iface) {
                [$rx, $tx] = $this->getInterfaceTraffic($client, $iface->interface_name);
                $iface->update([
                    'rx_bps' => $rx,
                    'tx_bps' => $tx,
                    'traffic_polled_at' => $now,
                ]);
            }
        } finally {
            $client->disconnect();
        }
    }

    protected function connectClient(string $host, int $port, string $user, string $pass): MikrotikApiClient
    {
        $client = new MikrotikApiClient;
        $client->connect($host, $port, $user, $pass, 8);

        return $client;
    }

    /** @return array{0: int, 1: int} */
    protected function fetchTrafficRates(MikrotikApiClient $client, Router $router): array
    {
        $download = 0;
        $upload = 0;
        $monitoredNames = $router->monitoredInterfaces->pluck('interface_name')->all();

        if ($monitoredNames !== []) {
            foreach ($monitoredNames as $name) {
                [$rx, $tx] = $this->getInterfaceTraffic($client, $name);
                $download += $rx;
                $upload += $tx;
            }

            return [$download, $upload];
        }

        $maxInterfaces = 3;
        $count = 0;

        foreach ($this->reRecords($client->query('/interface/print')) as $iface) {
            if ($count >= $maxInterfaces) {
                break;
            }

            if (($iface['running'] ?? '') !== 'true' || ($iface['disabled'] ?? '') === 'true') {
                continue;
            }

            $name = $iface['name'] ?? '';
            if ($name === '' || $name === 'lo') {
                continue;
            }

            try {
                [$rx, $tx] = $this->getInterfaceTraffic($client, $name);
                $download += $rx;
                $upload += $tx;
                $count++;
            } catch (Throwable) {
                continue;
            }
        }

        return [$download, $upload];
    }

    /** @return array{0: int, 1: int} */
    protected function getInterfaceTraffic(MikrotikApiClient $client, string $interface): array
    {
        foreach ($this->reRecords($client->query('/interface/monitor-traffic', [
            'interface' => $interface,
            'once' => '',
        ])) as $stat) {
            if (isset($stat['rx-bits-per-second']) || ($stat['!type'] ?? '') === 're') {
                return [
                    (int) ($stat['rx-bits-per-second'] ?? 0),
                    (int) ($stat['tx-bits-per-second'] ?? 0),
                ];
            }
        }

        return [0, 0];
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<string, string>
     */
    protected function firstReRecord(array $rows): array
    {
        foreach ($rows as $row) {
            if (($row['!type'] ?? '') === 're') {
                return $row;
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array<string, string>>
     */
    protected function reRecords(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row) => ($row['!type'] ?? '') === 're'
        ));
    }

    protected function friendlyError(Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'errno=10054') || str_contains($message, 'forcibly closed')) {
            return 'Koneksi ditutup oleh MikroTik — periksa port API, firewall, dan layanan API.';
        }

        if (str_contains($message, 'Autentikasi MikroTik gagal')) {
            return $message;
        }

        return $message;
    }

    protected function simulateMetrics(Router $router, ?bool $online = null): array
    {
        if ($router->name === 'RT-POP-03') {
            return [
                'status' => 'offline',
                'cpu' => 0,
                'memory' => 0,
                'temperature' => 0,
                'clients' => 0,
                'pppoe_sessions' => 0,
                'download_bps' => 0,
                'upload_bps' => 0,
                'uptime' => '-',
            ];
        }

        if ($online === false) {
            return [
                'status' => 'offline',
                'cpu' => 0,
                'memory' => 0,
                'temperature' => 0,
                'clients' => 0,
                'pppoe_sessions' => 0,
                'download_bps' => 0,
                'upload_bps' => 0,
                'uptime' => '-',
            ];
        }

        return [
            'status' => 'online',
            'cpu' => rand(25, 85),
            'memory' => rand(40, 90),
            'temperature' => rand(34, 52),
            'voltage' => rand(23, 25),
            'clients' => rand(100, 900),
            'pppoe_sessions' => rand(80, 500),
            'download_bps' => rand(50_000_000, 500_000_000),
            'upload_bps' => rand(20_000_000, 200_000_000),
            'uptime' => rand(5, 60).'d '.rand(1, 23).'h',
        ];
    }
}
