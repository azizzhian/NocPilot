<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RouterInterfaceResource;
use App\Http\Resources\RouterResource;
use App\Models\Router;
use App\Models\RouterInterface;
use App\Services\Monitoring\RouterMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MonitoringController extends Controller
{
    public function __construct(private RouterMonitor $monitor) {}

    public function summary(): JsonResponse
    {
        $online = Router::where('status', 'online');
        $onlineCount = (clone $online)->count();
        $offlineCount = Router::where('status', 'offline')->count();

        return response()->json([
            'router_online' => $onlineCount,
            'router_offline' => $offlineCount,
            'router_total' => Router::count(),
            'cpu_average' => (int) round((clone $online)->avg('cpu') ?? 0),
            'memory_average' => (int) round((clone $online)->avg('memory') ?? 0),
            'temperature_average' => (int) round((clone $online)->avg('temperature') ?? 0),
            'total_clients' => (int) Router::sum('clients'),
            'total_pppoe' => (int) Router::sum('pppoe_sessions'),
            'total_download_mbps' => round(Router::sum('download_bps') / 1_000_000, 1),
            'total_upload_mbps' => round(Router::sum('upload_bps') / 1_000_000, 1),
        ]);
    }

    public function routers(Request $request): AnonymousResourceCollection
    {
        $query = Router::query()
            ->with(['interfaces' => fn ($q) => $q->orderBy('interface_name')])
            ->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%");
            });
        }

        if ($pop = $request->string('pop')->toString()) {
            $query->where('pop', 'like', "%{$pop}%");
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return RouterResource::collection($query->get());
    }

    public function show(Router $router): RouterResource
    {
        $router->load(['interfaces' => fn ($q) => $q->orderBy('interface_name')]);

        return new RouterResource($router);
    }

    public function sync(Router $router): JsonResponse
    {
        $success = $this->monitor->sync($router);

        return response()->json([
            'message' => $success ? 'Sinkronisasi berhasil.' : 'Sinkronisasi gagal.',
            'data' => new RouterResource($router->fresh()),
        ], $success ? 200 : 422);
    }

    public function syncAll(): JsonResponse
    {
        $results = $this->monitor->syncAll();

        return response()->json([
            'message' => "Sinkronisasi selesai. {$results['success']} berhasil, {$results['failed']} gagal.",
            'results' => $results,
        ]);
    }

    public function pops(): JsonResponse
    {
        $pops = Router::whereNotNull('pop')
            ->distinct()
            ->orderBy('pop')
            ->pluck('pop');

        return response()->json(['data' => $pops]);
    }

    /**
     * Snapshot ringan ala Zabbix: hanya baca DB, tanpa SNMP/API ke perangkat.
     */
    public function trafficSnapshot(): JsonResponse
    {
        $interfaces = RouterInterface::query()
            ->where('is_monitored', true)
            ->orderBy('interface_name')
            ->get([
                'id',
                'router_id',
                'interface_name',
                'label',
                'is_running',
                'rx_bps',
                'tx_bps',
                'traffic_polled_at',
            ]);

        $routers = [];
        foreach ($interfaces as $iface) {
            $routers[$iface->router_id][] = [
                'interface_name' => $iface->interface_name,
                'label' => $iface->label ?: $iface->interface_name,
                'rx_bps' => (int) ($iface->rx_bps ?? 0),
                'tx_bps' => (int) ($iface->tx_bps ?? 0),
                'rx_mbps' => round(((int) ($iface->rx_bps ?? 0)) / 1_000_000, 2),
                'tx_mbps' => round(((int) ($iface->tx_bps ?? 0)) / 1_000_000, 2),
                'is_running' => (bool) $iface->is_running,
                'traffic_polled_at' => $iface->traffic_polled_at?->toIso8601String(),
            ];
        }

        return response()->json([
            'polled_at' => now()->toIso8601String(),
            'source' => 'collector',
            'routers' => $routers,
        ]);
    }

    public function syncInterfaces(Router $router): JsonResponse
    {
        try {
            $count = $this->monitor->syncInterfaces($router);
            $router->load(['interfaces' => fn ($q) => $q->orderBy('interface_name')]);

            return response()->json([
                'message' => "{$count} interface disinkronkan.",
                'count' => $count,
                'data' => RouterInterfaceResource::collection(
                    $router->interfaces->sortBy(fn (RouterInterface $i) => $this->interfaceSortKey($i->interface_name))->values()
                ),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function updateInterface(Request $request, Router $router, RouterInterface $interface): JsonResponse
    {
        if ($interface->router_id !== $router->id) {
            abort(404);
        }

        $data = $request->validate([
            'is_monitored' => 'required|boolean',
            'label' => 'nullable|string|max:255',
        ]);

        $interface->update($data);

        return response()->json([
            'message' => $data['is_monitored']
                ? "Interface {$interface->interface_name} dimonitor."
                : "Interface {$interface->interface_name} tidak dimonitor.",
            'data' => new RouterInterfaceResource($interface->fresh()),
        ]);
    }

    public function live(Router $router): JsonResponse
    {
        try {
            return response()->json($this->monitor->liveMetrics($router));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    protected function interfaceSortKey(string $name): string
    {
        $lower = strtolower($name);
        $score = match (true) {
            str_starts_with($lower, 'sfp'), str_starts_with($lower, 'qsfp'), str_starts_with($lower, 'ether') => 0,
            str_starts_with($lower, 'bridge'), str_starts_with($lower, 'vlan'), str_starts_with($lower, 'bond') => 1,
            str_contains($lower, 'pppoe') => 9,
            default => 5,
        };

        return sprintf('%d-%s', $score, $lower);
    }
}
