<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyReportSnapshot;
use App\Models\User;
use App\Services\Report\NetworkMonitorReportService;
use App\Services\Report\ReportGeneratorService;
use App\Services\Report\ReportTemplateService;
use App\Support\ReportTemplateDefaults;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerateReportController extends Controller
{
    public function index(Request $request, ReportTemplateService $templates): JsonResponse
    {
        $date = $request->string('date', now()->toDateString())->toString();

        $snapshot = DailyReportSnapshot::whereDate('report_date', $date)
            ->latest('id')
            ->first();

        $nocUsers = User::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'date' => $date,
            'snapshot' => $snapshot,
            'noc_users' => $nocUsers,
            'default_responsible' => $request->user()->name,
            'activity_name' => \App\Support\AppSetting::get(
                'activity_name',
                config('app.activity_name', 'Report Monitoring & Aktivasi Broadband'),
            ),
            'templates' => $templates->all(),
        ]);
    }

    public function generate(
        Request $request,
        ReportGeneratorService $generator,
        NetworkMonitorReportService $monitorReport,
    ): JsonResponse {
        $data = $request->validate([
            'report_date' => 'required|date',
            'responsible_name' => 'required|string|max:255',
        ]);

        $date = Carbon::parse($data['report_date']);

        // Monitoring memakai snapshot DB terakhir (di-update job/scheduler), tanpa syncAll.
        try {
            $daily = $generator->generateDailyReport($date, $data['responsible_name']);
            $noc = $generator->generateNocUpdate($date);
            $monitoring = $monitorReport->generate();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Gagal generate report: '.$e->getMessage(),
            ], 422);
        }

        $activityName = \App\Support\AppSetting::get(
            'activity_name',
            config('app.activity_name', 'Report Monitoring & Aktivasi Broadband'),
        );

        $snapshot = DailyReportSnapshot::create([
            'report_date' => $date,
            'generated_by' => $request->user()->id,
            'responsible_name' => $data['responsible_name'],
            'activity_name' => $activityName,
            'daily_report_text' => $daily,
            'noc_update_text' => $noc,
            'monitoring_report_text' => $monitoring,
        ]);

        return response()->json([
            'message' => 'Report berhasil di-generate.',
            'snapshot' => $snapshot->load('generator:id,name'),
            'daily_report_text' => $daily,
            'noc_update_text' => $noc,
            'monitoring_report_text' => $monitoring,
        ]);
    }

    public function generateSection(
        Request $request,
        ReportGeneratorService $generator,
        NetworkMonitorReportService $monitorReport,
    ): JsonResponse {
        $data = $request->validate([
            'section' => 'required|in:complaint,activation,cctv,noc,dismantle,ticket,monitoring',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'report_date' => 'nullable|date',
        ]);

        $section = $data['section'];
        $fromRaw = $data['from'] ?? $data['report_date'] ?? now()->toDateString();
        $toRaw = $data['to'] ?? $data['from'] ?? $data['report_date'] ?? $fromRaw;
        $from = Carbon::parse($fromRaw)->startOfDay();
        $to = Carbon::parse($toRaw)->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        try {
            // Monitoring: pakai data DB terakhir (job/scheduler), tanpa syncAll agar tidak timeout.
            $text = $section === 'monitoring'
                ? $monitorReport->generate()
                : $generator->generateSection($section, $from, $to);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Gagal generate report: '.$e->getMessage(),
            ], 422);
        }

        $labels = [
            'complaint' => 'Komplain',
            'activation' => 'Aktivasi',
            'cctv' => 'CCTV',
            'noc' => 'Update NOC',
            'dismantle' => 'Dismantle',
            'ticket' => 'Ticket',
            'monitoring' => 'Monitoring',
        ];

        return response()->json([
            'message' => 'Report '.($labels[$section] ?? $section).' berhasil di-generate.',
            'section' => $section,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'report_date' => $from->toDateString(),
            'text' => $text,
        ]);
    }

    public function history(): JsonResponse
    {
        $snapshots = DailyReportSnapshot::with('generator:id,name')
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($snapshots);
    }

    public function show(DailyReportSnapshot $snapshot): JsonResponse
    {
        return response()->json([
            'data' => $snapshot->load('generator:id,name'),
        ]);
    }

    public function updateTemplate(Request $request, ReportTemplateService $templates): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:daily,noc,monitoring',
            'body' => 'required|string|max:50000',
        ]);

        $templates->save($data['type'], $data['body']);

        return response()->json(['message' => 'Template berhasil disimpan.', 'ok' => true]);
    }

    public function resetTemplate(Request $request, ReportTemplateService $templates): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:daily,noc,monitoring',
        ]);

        $templates->reset($data['type']);

        return response()->json([
            'message' => 'Template dikembalikan ke default.',
            'ok' => true,
            'body' => ReportTemplateDefaults::body($data['type']),
        ]);
    }
}
