<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Onu;
use App\Models\RealtimeEvent;
use App\Models\Router;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    public function index(): JsonResponse
    {
        $events = RealtimeEvent::query()
            ->where('channel', 'noc')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'message' => $e->message,
                'severity' => $e->severity,
                'created_at' => $e->created_at?->toIso8601String(),
            ]);

        $offlineRouters = Router::where('status', 'offline')->get(['id', 'name', 'ip', 'pop']);
        $highCpuRouters = Router::where('status', 'online')->where('cpu', '>', 80)->get(['id', 'name', 'cpu']);
        $onuLos = Onu::whereIn('status', ['los', 'offline'])->count();

        $issues = collect();

        foreach ($offlineRouters as $router) {
            $issues->push([
                'id' => "router-offline-{$router->id}",
                'name' => "Router Offline — {$router->name}",
                'condition' => "{$router->ip} · {$router->pop}",
                'status' => 'active',
                'triggered' => 1,
                'severity' => 'critical',
            ]);
        }

        foreach ($highCpuRouters as $router) {
            $issues->push([
                'id' => "router-cpu-{$router->id}",
                'name' => "CPU Tinggi — {$router->name}",
                'condition' => "CPU {$router->cpu}%",
                'status' => 'active',
                'triggered' => 1,
                'severity' => 'warning',
            ]);
        }

        if ($onuLos > 0) {
            $issues->push([
                'id' => 'onu-los',
                'name' => 'ONU LOS / Offline',
                'condition' => "{$onuLos} perangkat bermasalah",
                'status' => 'active',
                'triggered' => $onuLos,
                'severity' => 'warning',
            ]);
        }

        return response()->json([
            'events' => $events,
            'issues' => $issues->values(),
            'counts' => [
                'router_offline' => $offlineRouters->count(),
                'high_cpu' => $highCpuRouters->count(),
                'onu_problem' => $onuLos,
            ],
        ]);
    }
}
