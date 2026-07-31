<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RouterResource;
use App\Models\Router;
use App\Services\Monitoring\RouterMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RouterController extends Controller
{
    public function __construct(private RouterMonitor $monitor) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Router::query()->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%")
                    ->orWhere('pop', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return RouterResource::collection($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $router = Router::create([
            ...$data,
            'api_port' => $data['api_port'] ?? 8728,
            'snmp_port' => $data['snmp_port'] ?? 161,
            'snmp_timeout' => $data['snmp_timeout'] ?? 3,
            'snmp_version' => $data['snmp_version'] ?? '2c',
            'monitor_via' => $data['monitor_via'] ?? 'api',
            'status' => 'offline',
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->monitor->sync($router->fresh());

        return response()->json([
            'message' => 'Router berhasil ditambahkan dan disinkronkan.',
            'data' => new RouterResource($router->fresh()),
        ], 201);
    }

    public function show(Router $router): RouterResource
    {
        return new RouterResource($router);
    }

    public function update(Request $request, Router $router): JsonResponse
    {
        $data = $this->validated($request, updating: true);

        if (array_key_exists('password', $data) && $data['password'] === '') {
            unset($data['password']);
        }

        if (array_key_exists('snmp_community', $data) && $data['snmp_community'] === '') {
            unset($data['snmp_community']);
        }

        $router->update($data);

        $connectionChanged = array_key_exists('ip', $data)
            || array_key_exists('api_port', $data)
            || array_key_exists('username', $data)
            || array_key_exists('password', $data)
            || array_key_exists('monitor_via', $data)
            || array_key_exists('snmp_community', $data)
            || array_key_exists('snmp_port', $data)
            || array_key_exists('snmp_version', $data)
            || array_key_exists('snmp_timeout', $data);

        if ($connectionChanged) {
            $this->monitor->sync($router->fresh());
        }

        return response()->json([
            'message' => $connectionChanged
                ? 'Router diperbarui dan disinkronkan ulang.'
                : 'Router berhasil diperbarui.',
            'data' => new RouterResource($router->fresh()),
        ]);
    }

    public function destroy(Router $router): JsonResponse
    {
        $router->delete();

        return response()->json(['message' => 'Router berhasil dihapus.']);
    }

    public function testConnection(Request $request, Router $router): JsonResponse
    {
        $data = $request->validate([
            'monitor_via' => 'nullable|in:api,snmp',
            'username' => 'nullable|string|max:100',
            'password' => 'nullable|string|max:255',
            'ip' => 'nullable|ip',
            'api_port' => 'nullable|integer|min:1|max:65535',
            'snmp_community' => 'nullable|string|max:255',
            'snmp_port' => 'nullable|integer|min:1|max:65535',
        ]);

        try {
            $result = $this->monitor->testConnection($router, $data);
            $router->update(['sync_error' => null]);

            return response()->json($result);
        } catch (\Throwable $e) {
            $via = $data['monitor_via'] ?? $router->monitor_via ?? 'api';

            if ($via === 'snmp') {
                $community = $data['snmp_community'] ?? null;
                $fromInput = $community !== null && $community !== '';

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'host' => $data['ip'] ?? $router->ip,
                    'port' => $data['snmp_port'] ?? $router->snmp_port ?? 161,
                    'community_source' => $fromInput ? 'input' : 'stored',
                ], 422);
            }

            $password = $data['password'] ?? null;
            $username = $data['username'] ?? null;
            $passwordFromInput = $password !== null && $password !== '';
            $usernameFromInput = $username !== null && $username !== '';

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'host' => $data['ip'] ?? $router->ip,
                'port' => $data['api_port'] ?? $router->api_port ?? 8728,
                'username' => $usernameFromInput ? $username : $router->username,
                'username_source' => $usernameFromInput ? 'input' : 'stored',
                'password_source' => $passwordFromInput ? 'input' : 'stored',
                'password_length' => $passwordFromInput ? strlen($password) : strlen($router->password ?? ''),
            ], 422);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $updating = false): array
    {
        $rules = [
            'name' => ($updating ? 'sometimes' : 'required').'|string|max:255',
            'ip' => ($updating ? 'sometimes' : 'required').'|ip',
            'monitor_via' => 'nullable|in:api,snmp',
            'api_port' => 'nullable|integer|min:1|max:65535',
            'username' => 'nullable|string|max:100',
            'password' => 'nullable|string|max:255',
            'snmp_version' => 'nullable|in:2c',
            'snmp_community' => 'nullable|string|max:255',
            'snmp_port' => 'nullable|integer|min:1|max:65535',
            'snmp_timeout' => 'nullable|integer|min:1|max:30',
            'pop' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ];

        if (! $updating) {
            $rules['password'] = 'required_with:username|nullable|string|max:255';
            $rules['snmp_community'] = 'required_if:monitor_via,snmp|nullable|string|max:255';
        }

        return $request->validate($rules);
    }
}
