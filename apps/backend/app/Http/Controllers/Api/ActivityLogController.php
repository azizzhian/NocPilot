<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'scope' => ['nullable', Rule::in(['audit', 'activity'])],
            'type' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ActivityLog::query()->latest();
        $this->applyScope($query, $request->string('scope')->toString());

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        return ActivityLogResource::collection(
            $query->paginate(min($request->integer('per_page', 20), 100)),
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'scope' => ['nullable', Rule::in(['audit', 'activity'])],
            'type' => ['nullable', 'string', 'max:50'],
        ]);

        $query = ActivityLog::query()->latest();
        $this->applyScope($query, $request->string('scope')->toString());

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        $scope = $request->string('scope')->toString() ?: 'all';
        $filename = "{$scope}-log-".now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Waktu', 'Tipe', 'User', 'Aktivitas', 'IP', 'Browser', 'Device']);

            $query->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->type,
                        $log->user_name,
                        $log->action,
                        $log->ip_address,
                        $log->browser,
                        $log->device,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function applyScope($query, string $scope): void
    {
        if ($scope === 'audit') {
            $query->whereIn('type', config('activity_log.audit_types', []));

            return;
        }

        if ($scope === 'activity') {
            $query->whereIn('type', config('activity_log.activity_types', []));
        }
    }
}
