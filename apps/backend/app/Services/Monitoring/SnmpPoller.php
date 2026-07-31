<?php

namespace App\Services\Monitoring;

use App\Models\Router;
use App\Models\RouterInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SnmpPoller
{
    private const OID_SYS_DESCR = '1.3.6.1.2.1.1.1.0';

    private const OID_SYS_UPTIME = '1.3.6.1.2.1.1.3.0';

    private const OID_SYS_NAME = '1.3.6.1.2.1.1.5.0';

    private const OID_IF_DESCR = '1.3.6.1.2.1.2.2.1.2';

    private const OID_IF_OPER = '1.3.6.1.2.1.2.2.1.8';

    private const OID_IF_MIB_NAME = '1.3.6.1.2.1.31.1.1.1.1';

    private const OID_IF_ALIAS = '1.3.6.1.2.1.31.1.1.1.18';

    private const OID_IF_IN_OCTETS = '1.3.6.1.2.1.2.2.1.10';

    private const OID_IF_OUT_OCTETS = '1.3.6.1.2.1.2.2.1.16';

    private const OID_IF_HC_IN = '1.3.6.1.2.1.31.1.1.1.6';

    private const OID_IF_HC_OUT = '1.3.6.1.2.1.31.1.1.1.10';

    private const OID_HR_PROCESSOR = '1.3.6.1.2.1.25.3.3.1.2';

    private const OID_MT_TEMP = '1.3.6.1.4.1.14988.1.1.3.10.0';

    private const OID_MT_VOLTAGE = '1.3.6.1.4.1.14988.1.1.3.8.0';

    public function sync(Router $router): bool
    {
        try {
            $this->assertAvailable();
            $metrics = $this->fetchMetrics($router);

            $router->update([
                ...$metrics,
                'last_synced_at' => now(),
                'sync_error' => null,
            ]);

            if ($router->interfaces()->count() === 0) {
                try {
                    $this->syncInterfaces($router->fresh());
                } catch (Throwable $e) {
                    Log::warning("SNMP interface sync skipped for {$router->name}: {$e->getMessage()}");
                }
            }

            try {
                $this->persistMonitoredTraffic($router->fresh());
            } catch (Throwable $e) {
                Log::warning("SNMP traffic persist skipped for {$router->name}: {$e->getMessage()}");
            }

            return true;
        } catch (Throwable $e) {
            Log::warning("SNMP sync failed for {$router->name}: {$e->getMessage()}");

            // Jangan pakai $router->update() di sini: attribute kotor (mis. temperature OOR)
            // dari percobaan sync sebelumnya bisa ikut tertulis lagi dan gagal.
            Router::whereKey($router->id)->update([
                'status' => 'offline',
                'sync_error' => $this->friendlyError($e),
                'last_synced_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * @return array{success: bool, message: string, latency_ms: int, identity: string|null, host: string, port: int, community_source: string}
     */
    public function testConnection(
        Router $router,
        ?string $community = null,
        ?string $host = null,
        ?int $port = null,
    ): array {
        $this->assertAvailable();

        $communityFromInput = $community !== null && $community !== '';
        $community = $communityFromInput ? $community : (string) $router->snmp_community;
        $host = $host ?: $router->ip;
        $port = $port ?: ($router->snmp_port ?? 161);

        if ($community === '') {
            throw new RuntimeException('SNMP community wajib diisi.');
        }

        $started = microtime(true);
        $sysName = $this->get($host, $community, self::OID_SYS_NAME, $port, $router->snmp_timeout ?? 3);

        if ($sysName === null || $sysName === '') {
            throw new RuntimeException('Tidak ada respons SNMP dari perangkat.');
        }

        return [
            'success' => true,
            'message' => 'Koneksi SNMP berhasil.',
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'identity' => $sysName,
            'host' => $host,
            'port' => $port,
            'community_source' => $communityFromInput ? 'input' : 'stored',
        ];
    }

    public function syncInterfaces(Router $router): int
    {
        $remote = $this->fetchInterfaces($router);
        if ($remote === []) {
            throw new RuntimeException('SNMP tidak mengembalikan daftar interface. Periksa community, firewall UDP 161, dan timeout.');
        }

        $remoteByName = [];
        foreach ($remote as $iface) {
            $remoteByName[$iface['name']] = $iface;
        }
        $remoteNames = array_keys($remoteByName);

        $router->load('interfaces');
        foreach ($router->interfaces as $existing) {
            if (isset($remoteByName[$existing->interface_name])) {
                continue;
            }

            $match = $this->findBestRemoteName($existing->interface_name, $remoteNames);
            if ($match === null) {
                continue;
            }

            $conflict = $router->interfaces->first(
                fn (RouterInterface $row) => $row->id !== $existing->id && $row->interface_name === $match
            );

            if ($conflict) {
                if ($existing->is_monitored && ! $conflict->is_monitored) {
                    $conflict->update([
                        'is_monitored' => true,
                        'label' => $conflict->label ?: $existing->label,
                    ]);
                }
                $existing->delete();
                continue;
            }

            $existing->update([
                'interface_name' => $match,
                'label' => $existing->label ?: $this->commentFromApiName($existing->interface_name, $match),
            ]);
        }

        $count = 0;
        foreach ($remote as $iface) {
            $row = RouterInterface::firstOrNew([
                'router_id' => $router->id,
                'interface_name' => $iface['name'],
            ]);

            $row->is_running = $iface['running'];
            if (! filled($row->label) && filled($iface['alias'])) {
                $row->label = $iface['alias'];
            }
            $row->save();
            $count++;
        }

        // Hapus nama lama yang sudah tidak ada di perangkat (termasuk sisa rename API: *-Upstream, dll).
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
        $this->assertAvailable();
        $router->load(['monitoredInterfaces']);

        $community = (string) $router->snmp_community;
        $host = $router->ip;
        $port = $router->snmp_port ?? 161;
        $timeout = max(5, $router->snmp_timeout ?? 3);

        // Baca dari DB dulu (hasil collector). Jangan SNMP dari request UI.
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

        $traffic = $this->sampleLiveTraffic($router, $host, $community, $port, $timeout);

        return [
            'polled_at' => now()->toIso8601String(),
            'source' => 'snmp',
            'traffic' => $traffic,
        ];
    }

    /**
     * Collector: hitung rate interface terpantau lalu simpan ke DB (ala Zabbix history/latest).
     */
    public function persistMonitoredTraffic(Router $router): void
    {
        $this->assertAvailable();
        $router->loadMissing('monitoredInterfaces');
        if ($router->monitoredInterfaces->isEmpty()) {
            return;
        }

        $community = (string) $router->snmp_community;
        $host = $router->ip;
        $port = $router->snmp_port ?? 161;
        $timeout = max(5, $router->snmp_timeout ?? 3);

        // Sampel 1 (isi cache counter)
        $this->sampleLiveTraffic($router, $host, $community, $port, $timeout);
        usleep(1_000_000);
        // Sampel 2 (hitung rate)
        $traffic = $this->sampleLiveTraffic($router, $host, $community, $port, $timeout);
        $now = now();

        foreach ($traffic as $row) {
            // Jangan timpa nilai lama dengan 0 kalau sample gagal/sebagian.
            if (($row['rx_bps'] ?? 0) === 0 && ($row['tx_bps'] ?? 0) === 0) {
                continue;
            }

            RouterInterface::query()
                ->where('router_id', $router->id)
                ->where('interface_name', $row['interface_name'])
                ->update([
                    'rx_bps' => $row['rx_bps'],
                    'tx_bps' => $row['tx_bps'],
                    'is_running' => $row['is_running'],
                    'traffic_polled_at' => $now,
                ]);
        }
    }

    /**
     * @return array<int, array{interface_name: string, label: string, rx_bps: int, tx_bps: int, rx_mbps: float, tx_mbps: float, is_running: bool}>
     */
    protected function sampleLiveTraffic(Router $router, string $host, string $community, int $port, int $timeout): array
    {
        $now = microtime(true);
        $ifIndexByName = $this->mapInterfaceIndexes($host, $community, $port, $timeout);

        $traffic = [];
        foreach ($router->monitoredInterfaces as $iface) {
            $index = $ifIndexByName[$iface->interface_name]
                ?? $this->resolveInterfaceIndex($iface->interface_name, $ifIndexByName);
            $rx = 0;
            $tx = 0;

            if ($index !== null) {
                [$in, $out] = $this->readInterfaceOctets($host, $community, $index, $port, $timeout);
                [$rx, $tx] = $this->rateFromCounters($router->id, $iface->interface_name, $in, $out, $now);
            }

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

        return $traffic;
    }

    /** @return array<string, mixed> */
    protected function fetchMetrics(Router $router): array
    {
        $community = (string) $router->snmp_community;
        $host = $router->ip;
        $port = $router->snmp_port ?? 161;
        $timeout = $router->snmp_timeout ?? 3;

        $sysName = $this->get($host, $community, self::OID_SYS_NAME, $port, $timeout);
        if ($sysName === null) {
            throw new RuntimeException('Tidak ada respons SNMP (sysName).');
        }

        $sysDescr = $this->get($host, $community, self::OID_SYS_DESCR, $port, $timeout) ?? '';
        $uptimeTicks = $this->getCounter($host, $community, self::OID_SYS_UPTIME, $port, $timeout) ?? 0;
        $cpu = $this->averageProcessorLoad($host, $community, $port, $timeout);
        $temp = $this->getFloat($host, $community, self::OID_MT_TEMP, $port, $timeout);
        $voltage = $this->getFloat($host, $community, self::OID_MT_VOLTAGE, $port, $timeout);

        [$download, $upload] = $this->aggregateMonitoredTraffic($router, $host, $community, $port, $timeout);

        return [
            'status' => 'online',
            'board' => $sysName,
            'version' => $this->shortDescr($sysDescr),
            'uptime' => $this->formatUptime($uptimeTicks),
            'cpu' => $this->clampInt($cpu, 0, 100),
            'memory' => 0,
            'temperature' => $this->normalizeTemperature($temp),
            'voltage' => $voltage === null ? null : $this->clampInt((int) round($voltage), 0, 65535),
            'clients' => 0,
            'pppoe_sessions' => 0,
            'download_bps' => $download,
            'upload_bps' => $upload,
            'license' => null,
        ];
    }

    protected function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    /** MikroTik kadang kirim nilai sensor aneh (mis. 255) — anggap tidak valid. */
    protected function normalizeTemperature(?float $temp): int
    {
        if ($temp === null) {
            return 0;
        }
        $value = (int) round($temp);
        if ($value < 0 || $value > 120) {
            return 0;
        }

        return $value;
    }

    /**
     * @return array<int, array{name: string, alias: string|null, running: bool}>
     */
    protected function fetchInterfaces(Router $router): array
    {
        $community = (string) $router->snmp_community;
        $host = $router->ip;
        $port = $router->snmp_port ?? 161;
        $timeout = max(5, $router->snmp_timeout ?? 3);

        $names = $this->walk($host, $community, self::OID_IF_MIB_NAME, $port, $timeout);
        $descr = $names === []
            ? $this->walk($host, $community, self::OID_IF_DESCR, $port, $timeout)
            : [];
        $source = $names !== [] ? $names : $descr;

        if ($source === []) {
            // Fallback: try ifDescr even if ifName returned empty after partial failure.
            $source = $this->walk($host, $community, self::OID_IF_DESCR, $port, $timeout);
        }

        $oper = $this->walk($host, $community, self::OID_IF_OPER, $port, $timeout);
        $alias = $this->walk($host, $community, self::OID_IF_ALIAS, $port, $timeout);

        $result = [];
        foreach ($source as $oid => $name) {
            $index = $this->oidIndex($oid);
            $name = $this->normalizeInterfaceName($name);
            if ($index === null || $name === '' || $name === 'lo') {
                continue;
            }

            $status = $oper[self::OID_IF_OPER.'.'.$index] ?? '2';
            $aliasValue = $alias[self::OID_IF_ALIAS.'.'.$index] ?? null;
            $aliasValue = $aliasValue !== null && $aliasValue !== ''
                ? $this->normalizeInterfaceName($aliasValue)
                : null;

            $result[] = [
                'name' => $name,
                'alias' => $aliasValue && strcasecmp($aliasValue, $name) !== 0 ? $aliasValue : null,
                'running' => (int) $status === 1,
            ];
        }

        usort($result, function (array $a, array $b) {
            return $this->interfaceSortScore($a['name']) <=> $this->interfaceSortScore($b['name'])
                ?: strcmp($a['name'], $b['name']);
        });

        return $result;
    }

    /** @return array<string, int> */
    protected function mapInterfaceIndexes(string $host, string $community, int $port, int $timeout): array
    {
        $timeout = max(5, $timeout);
        $names = $this->walk($host, $community, self::OID_IF_MIB_NAME, $port, $timeout);
        if ($names === []) {
            $names = $this->walk($host, $community, self::OID_IF_DESCR, $port, $timeout);
        }

        $map = [];
        foreach ($names as $oid => $name) {
            $index = $this->oidIndex($oid);
            $name = $this->normalizeInterfaceName($name);
            if ($index !== null && $name !== '') {
                $map[$name] = $index;
            }
        }

        return $map;
    }

    protected function resolveInterfaceIndex(string $interfaceName, array $map): ?int
    {
        if (isset($map[$interfaceName])) {
            return $map[$interfaceName];
        }

        foreach ($map as $name => $index) {
            if (strcasecmp($name, $interfaceName) === 0) {
                return $index;
            }
        }

        $match = $this->findBestRemoteName($interfaceName, array_keys($map));

        return $match !== null ? ($map[$match] ?? null) : null;
    }

    /**
     * @param  list<string>  $remoteNames
     */
    protected function findBestRemoteName(string $localName, array $remoteNames): ?string
    {
        foreach ($remoteNames as $remote) {
            if (strcasecmp($remote, $localName) === 0) {
                return $remote;
            }
        }

        // API MikroTik sering: "sfp-sfpplus1-Upstream" → SNMP: "sfp-sfpplus1"
        $candidates = [];
        foreach ($remoteNames as $remote) {
            if (
                str_starts_with(strtolower($localName), strtolower($remote).'-')
                || str_starts_with(strtolower($localName), strtolower($remote).' ')
            ) {
                $candidates[] = $remote;
            }
        }

        if ($candidates !== []) {
            usort($candidates, fn ($a, $b) => strlen($b) <=> strlen($a));

            return $candidates[0];
        }

        // Rename port: "ether11-UPSTREAM#2" → "ether11" (base port sama, unik)
        $localBase = $this->physicalBaseName($localName);
        if ($localBase === null) {
            return null;
        }

        $byBase = [];
        foreach ($remoteNames as $remote) {
            if ($this->physicalBaseName($remote) === $localBase) {
                $byBase[] = $remote;
            }
        }

        if (count($byBase) === 1) {
            return $byBase[0];
        }

        // Kalau SNMP juga pakai nama ber-suffix, cocokkan exact base di antara kandidat.
        foreach ($byBase as $remote) {
            if (strcasecmp($remote, $localBase) === 0) {
                return $remote;
            }
        }

        return null;
    }

    /** ether1 / sfp-sfpplus1 / vlan650 — tanpa suffix komentar rename. */
    protected function physicalBaseName(string $name): ?string
    {
        if (preg_match('/^(ether\d+|sfp-sfpplus\d+|qsfpplus\d+|sfp\d+|wlan\d+|vlan\d+)/i', $name, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    protected function commentFromApiName(string $oldName, string $remoteName): ?string
    {
        if (str_starts_with($oldName, $remoteName.'-')) {
            $comment = substr($oldName, strlen($remoteName) + 1);

            return $comment !== '' ? $comment : null;
        }

        $base = $this->physicalBaseName($oldName);
        if ($base !== null && str_starts_with(strtolower($oldName), $base.'-')) {
            $comment = substr($oldName, strlen($base) + 1);

            return $comment !== '' ? $comment : null;
        }

        return null;
    }

    protected function normalizeInterfaceName(string $name): string
    {
        $name = trim($name);
        // Hex-STRING SNMP biasanya ber-spasi: "73 66 70 2d ...". Jangan anggap Counter64
        // murni digit (mis. 13407079728304) sebagai hex — itu merusak traffic RX/TX.
        if (preg_match('/^(?:[0-9A-Fa-f]{2}\s+)+[0-9A-Fa-f]{2}$/', $name)) {
            $bytes = preg_split('/\s+/', trim($name)) ?: [];
            $decoded = '';
            foreach ($bytes as $byte) {
                if ($byte === '') {
                    continue;
                }
                $decoded .= chr((int) hexdec($byte));
            }
            $name = $decoded;
        }

        return trim($name, " \t\n\r\0\x0B\"");
    }

    protected function cleanSnmpValue(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^(STRING|Hex-STRING|INTEGER|Counter32|Counter64|Gauge32|Timeticks|Opaque):\s*/i', '', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B\"");

        if (preg_match('/^\((\d+)\)/', $value, $m)) {
            return $m[1];
        }

        // Counter/Gauge numerik: jangan lewat normalizeInterfaceName (bisa dianggap hex).
        if (preg_match('/^\d+$/', $value)) {
            return $value;
        }

        return $this->normalizeInterfaceName($value);
    }

    protected function interfaceSortScore(string $name): int
    {
        $lower = strtolower($name);
        if (str_starts_with($lower, 'sfp') || str_starts_with($lower, 'qsfp') || str_starts_with($lower, 'ether')) {
            return 0;
        }
        if (str_starts_with($lower, 'bridge') || str_starts_with($lower, 'vlan') || str_starts_with($lower, 'bond')) {
            return 1;
        }
        if (str_starts_with($lower, 'wlan') || str_starts_with($lower, 'lte')) {
            return 2;
        }
        if (str_contains($lower, 'pppoe') || str_starts_with($lower, '<pppoe')) {
            return 9;
        }

        return 5;
    }

    /** @return array{0: int, 1: int} */
    protected function aggregateMonitoredTraffic(Router $router, string $host, string $community, int $port, int $timeout): array
    {
        $router->loadMissing('monitoredInterfaces');
        if ($router->monitoredInterfaces->isEmpty()) {
            return [0, 0];
        }

        $map = $this->mapInterfaceIndexes($host, $community, $port, $timeout);
        $now = microtime(true);
        $rxTotal = 0;
        $txTotal = 0;

        foreach ($router->monitoredInterfaces as $iface) {
            $index = $this->resolveInterfaceIndex($iface->interface_name, $map);
            if ($index === null) {
                continue;
            }
            [$in, $out] = $this->readInterfaceOctets($host, $community, $index, $port, $timeout);
            [$rx, $tx] = $this->rateFromCounters($router->id, $iface->interface_name, $in, $out, $now);
            $rxTotal += $rx;
            $txTotal += $tx;
        }

        return [$rxTotal, $txTotal];
    }

    /** @return array{0: int|null, 1: int|null} */
    protected function readInterfaceOctets(string $host, string $community, int $index, int $port, int $timeout): array
    {
        $in = $this->getCounter($host, $community, self::OID_IF_HC_IN.'.'.$index, $port, $timeout)
            ?? $this->getCounter($host, $community, self::OID_IF_IN_OCTETS.'.'.$index, $port, $timeout);
        $out = $this->getCounter($host, $community, self::OID_IF_HC_OUT.'.'.$index, $port, $timeout)
            ?? $this->getCounter($host, $community, self::OID_IF_OUT_OCTETS.'.'.$index, $port, $timeout);

        return [$in, $out];
    }

    protected function parseCounterValue(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^([0-9]+)/', $raw, $m)) {
            return (int) $m[1];
        }
        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return null;
    }

    /** @return array{0: int, 1: int} */
    protected function rateFromCounters(int $routerId, string $iface, ?int $in, ?int $out, float $now): array
    {
        $cacheKey = 'snmp:counters:'.$routerId.':'.md5($iface);
        $prev = Cache::get($cacheKey);
        Cache::put($cacheKey, ['in' => $in, 'out' => $out, 'at' => $now], 600);

        if (! is_array($prev) || ! isset($prev['at'])) {
            return [0, 0];
        }

        $dt = max(0.5, $now - (float) $prev['at']);
        $rx = 0;
        $tx = 0;

        if ($in !== null && isset($prev['in']) && $prev['in'] !== null) {
            $prevIn = (int) $prev['in'];
            $din = $in >= $prevIn ? ($in - $prevIn) : $in;
            $rx = (int) round(($din * 8) / $dt);
        }

        if ($out !== null && isset($prev['out']) && $prev['out'] !== null) {
            $prevOut = (int) $prev['out'];
            $dout = $out >= $prevOut ? ($out - $prevOut) : $out;
            $tx = (int) round(($dout * 8) / $dt);
        }

        return [$rx, $tx];
    }

    protected function averageProcessorLoad(string $host, string $community, int $port, int $timeout): int
    {
        $walk = $this->walk($host, $community, self::OID_HR_PROCESSOR, $port, $timeout);
        if ($walk === []) {
            return 0;
        }

        $values = array_map('intval', array_values($walk));
        $values = array_filter($values, fn ($v) => $v >= 0 && $v <= 100);
        if ($values === []) {
            return 0;
        }

        return (int) round(array_sum($values) / count($values));
    }

    protected function get(string $host, string $community, string $oid, int $port, int $timeout): ?string
    {
        $this->configureSnmp($timeout);
        $value = @snmp2_get($host.':'.$port, $community, $oid, $timeout * 1_000_000, 1);
        if ($value === false || $value === null) {
            return null;
        }

        return $this->cleanSnmpValue((string) $value);
    }

    protected function getCounter(string $host, string $community, string $oid, int $port, int $timeout): ?int
    {
        $this->configureSnmp($timeout);
        $raw = @snmp2_get($host.':'.$port, $community, $oid, max(1, $timeout) * 1_000_000, 2);
        if ($raw === false || $raw === null) {
            return null;
        }

        return $this->parseCounterValue($this->cleanSnmpValue((string) $raw));
    }

    protected function getFloat(string $host, string $community, string $oid, int $port, int $timeout): ?float
    {
        $raw = $this->get($host, $community, $oid, $port, $timeout);
        if ($raw === null || ! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    /** @return array<string, string> */
    protected function walk(string $host, string $community, string $oid, int $port, int $timeout): array
    {
        $this->configureSnmp($timeout);
        $micro = max(1, $timeout) * 1_000_000;
        $result = @snmp2_real_walk($host.':'.$port, $community, $oid, $micro, 2);
        if (! is_array($result) || $result === []) {
            $result = @snmp2_walk($host.':'.$port, $community, $oid, $micro, 2);
        }
        if (! is_array($result)) {
            return [];
        }

        $out = [];
        foreach ($result as $key => $value) {
            $cleanOid = $this->normalizeOidKey((string) $key);
            $out[$cleanOid] = $this->cleanSnmpValue((string) $value);
        }

        return $out;
    }

    protected function normalizeOidKey(string $key): string
    {
        $key = trim($key);
        if (preg_match('/((?:\d+\.)+\d+)$/', $key, $m)) {
            return $m[1];
        }

        return $key;
    }

    protected function configureSnmp(int $timeoutSeconds): void
    {
        if (function_exists('snmp_set_valueretrieval')) {
            snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        }
        if (function_exists('snmp_set_quick_print')) {
            snmp_set_quick_print(true);
        }
        if (function_exists('snmp_set_oid_output_format')) {
            snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
        }
    }

    protected function oidIndex(string $oid): ?int
    {
        if (preg_match('/\.(\d+)$/', $oid, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    protected function formatUptime(int $timeticks): string
    {
        $seconds = intdiv(max(0, $timeticks), 100);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $mins = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return "{$days}d {$hours}h {$mins}m";
        }

        return "{$hours}h {$mins}m";
    }

    protected function shortDescr(string $descr): string
    {
        $descr = preg_replace('/\s+/', ' ', trim($descr)) ?? '';

        return mb_substr($descr, 0, 120);
    }

    protected function assertAvailable(): void
    {
        if (! function_exists('snmp2_get')) {
            throw new RuntimeException('Ekstensi PHP SNMP belum aktif. Aktifkan extension=snmp di php.ini.');
        }
    }

    protected function friendlyError(Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains(strtolower($msg), 'snmp')) {
            return $msg;
        }

        return 'SNMP gagal: '.$msg;
    }
}
