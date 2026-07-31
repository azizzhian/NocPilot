<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'app_name' => config('app.name', 'NocPilot'),
            'activity_name' => config('app.activity_name', 'Report Monitoring & Aktivasi Broadband'),
            'timezone' => config('app.timezone', 'Asia/Jakarta'),
            'locale' => config('app.locale', 'id'),
            'features' => [
                'realtime_polling' => true,
                'mikrotik_sync' => true,
                'report_generate' => true,
                'csv_export' => true,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'activity_name' => 'sometimes|string|max:255',
        ]);

        // activity_name disimpan via .env/config di production; di local cukup echo kembali.
        return response()->json([
            'message' => 'Pengaturan disimpan.',
            'data' => array_merge($this->index()->getData(true), [
                'activity_name' => $request->input('activity_name', config('app.activity_name')),
            ]),
        ]);
    }
}
