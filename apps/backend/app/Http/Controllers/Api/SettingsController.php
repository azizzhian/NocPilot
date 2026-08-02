<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /** @return list<string> */
    public static function defaultSidebarFavorites(): array
    {
        return [
            '/',
            '/komplain',
            '/report/ticket',
            '/aktivasi',
            '/update-noc',
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activity_name' => 'sometimes|string|max:255',
            'sidebar_favorites' => 'sometimes|array|max:20',
            'sidebar_favorites.*' => 'string|max:100',
        ]);

        if (array_key_exists('activity_name', $data)) {
            AppSetting::set('activity_name', trim($data['activity_name']));
        }

        if (array_key_exists('sidebar_favorites', $data)) {
            $paths = collect($data['sidebar_favorites'])
                ->map(fn ($p) => '/'.ltrim(trim((string) $p), '/'))
                ->map(fn ($p) => $p === '/' ? '/' : rtrim($p, '/'))
                ->filter(fn ($p) => $p === '/' || preg_match('#^/[a-z0-9\-_./]+$#i', $p))
                ->unique()
                ->values()
                ->all();

            AppSetting::set('sidebar_favorites', $paths);
        }

        return response()->json([
            'message' => 'Pengaturan disimpan.',
            'data' => $this->payload(),
        ]);
    }

    /** @return array<string, mixed> */
    protected function payload(): array
    {
        $favorites = AppSetting::get('sidebar_favorites');
        if (! is_array($favorites) || $favorites === []) {
            $favorites = self::defaultSidebarFavorites();
        }

        $favorites = array_values(array_filter(
            $favorites,
            fn ($p) => is_string($p) && $p !== '',
        ));

        return [
            'app_name' => config('app.name', 'NocPilot'),
            'activity_name' => (string) AppSetting::get(
                'activity_name',
                config('app.activity_name', 'Report Monitoring & Aktivasi Broadband'),
            ),
            'timezone' => config('app.timezone', 'Asia/Jakarta'),
            'locale' => config('app.locale', 'id'),
            'sidebar_favorites' => $favorites,
            'features' => [
                'realtime_polling' => true,
                'mikrotik_sync' => true,
                'report_generate' => true,
                'csv_export' => true,
            ],
        ];
    }
}
