<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DailyActivation;
use App\Models\DailyCctvSetup;
use App\Models\DailyComplaint;
use App\Models\DailyDismantle;
use App\Models\DailyNocUpdate;
use App\Models\ReportTicket;
use App\Models\User;
use App\Support\ReportStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function navBadges(): JsonResponse
    {
        $today = today();

        return response()->json([
            'nav_badges' => [
                'monitoring' => 0,
                'input_harian' => DailyComplaint::whereDate('report_date', $today)
                    ->where('status', ReportStatus::ON_PROGRESS)
                    ->count(),
                'activations' => DailyActivation::whereDate('report_date', $today)
                    ->where('status', ReportStatus::ON_PROGRESS)
                    ->count()
                    + DailyCctvSetup::whereDate('report_date', $today)
                        ->where('status', ReportStatus::ON_PROGRESS)
                        ->count(),
                'dismantles' => DailyDismantle::whereDate('report_date', $today)
                    ->where('status', ReportStatus::ON_PROGRESS)
                    ->count(),
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $period = $request->string('period', 'day')->toString();
        if (! in_array($period, ['day', 'week', 'month', 'year'], true)) {
            $period = 'day';
        }

        $anchor = Carbon::parse($request->string('date', now()->toDateString())->toString())
            ->timezone(config('app.timezone'));

        [$from, $to] = $this->periodRange($period, $anchor);
        $userId = $request->integer('user_id', 0) ?: null;

        $summary = $this->summaryForRange($from, $to, $userId);
        $nocPerformance = $this->nocPerformance($from, $to);
        $charts = $this->buildCharts($period, $from, $to, $userId, $summary, $nocPerformance);

        return response()->json([
            'period' => [
                'type' => $period,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $this->periodLabel($period, $from, $to),
            ],
            'summary' => $summary,
            'kpis' => [
                ['key' => 'activations', 'label' => 'Aktivasi', 'value' => $summary['activations'], 'color' => 'success', 'icon' => 'activation'],
                ['key' => 'activations_clear', 'label' => 'Aktivasi Clear', 'value' => $summary['activations_clear'], 'color' => 'success', 'icon' => 'activation'],
                ['key' => 'complaints', 'label' => 'Komplain', 'value' => $summary['complaints'], 'color' => 'danger', 'icon' => 'ticket'],
                ['key' => 'complaints_clear', 'label' => 'Komplain Clear', 'value' => $summary['complaints_clear'], 'color' => 'success', 'icon' => 'ticket'],
                ['key' => 'dismantles', 'label' => 'Dismantle', 'value' => $summary['dismantles'], 'color' => 'warning', 'icon' => 'dismantle'],
                ['key' => 'dismantles_clear', 'label' => 'Dismantle Clear', 'value' => $summary['dismantles_clear'], 'color' => 'info', 'icon' => 'dismantle'],
                ['key' => 'cctv', 'label' => 'CCTV', 'value' => $summary['cctv'], 'color' => 'primary', 'icon' => 'onu'],
                ['key' => 'noc_updates', 'label' => 'Update NOC', 'value' => $summary['noc_updates'], 'color' => 'info', 'icon' => 'router'],
            ],
            'noc_performance' => $nocPerformance,
            'charts' => $charts,
            'recent_activities' => $this->recentActivities(),
            'noc_users' => User::role(['noc', 'administrator', 'teknisi'])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
                ->values(),
            'nav_badges' => [
                'monitoring' => 0,
                'input_harian' => DailyComplaint::whereDate('report_date', today())
                    ->where('status', ReportStatus::ON_PROGRESS)
                    ->count(),
                'activations' => DailyActivation::whereDate('report_date', today())
                    ->where('status', ReportStatus::ON_PROGRESS)
                    ->count()
                    + DailyCctvSetup::whereDate('report_date', today())
                        ->where('status', ReportStatus::ON_PROGRESS)
                        ->count(),
                'dismantles' => DailyDismantle::whereDate('report_date', today())
                    ->where('status', ReportStatus::ON_PROGRESS)
                    ->count(),
            ],
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function periodRange(string $period, Carbon $anchor): array
    {
        return match ($period) {
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
            default => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
        };
    }

    protected function periodLabel(string $period, Carbon $from, Carbon $to): string
    {
        return match ($period) {
            'week' => 'Minggu '.$from->translatedFormat('d M').' – '.$to->translatedFormat('d M Y'),
            'month' => $from->translatedFormat('F Y'),
            'year' => $from->translatedFormat('Y'),
            default => $from->translatedFormat('l, d F Y'),
        };
    }

    /** @return array<string, int> */
    protected function summaryForRange(Carbon $from, Carbon $to, ?int $userId): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $activations = DailyActivation::query()
            ->whereBetween('report_date', [$fromDate, $toDate]);

        $complaints = DailyComplaint::query()
            ->whereBetween('report_date', [$fromDate, $toDate]);

        $dismantles = DailyDismantle::query()
            ->whereBetween('report_date', [$fromDate, $toDate]);

        $cctv = DailyCctvSetup::query()
            ->whereBetween('report_date', [$fromDate, $toDate]);

        $nocUpdates = DailyNocUpdate::query()
            ->whereBetween('report_date', [$fromDate, $toDate]);

        return [
            'activations' => (clone $activations)
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
            'activations_clear' => (clone $activations)
                ->where('status', ReportStatus::CLEAR)
                ->when($userId, fn ($q) => $q->where('cleared_by', $userId))
                ->count(),
            'complaints' => (clone $complaints)
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
            'complaints_clear' => (clone $complaints)
                ->where('status', ReportStatus::CLEAR)
                ->when($userId, fn ($q) => $q->where('cleared_by', $userId))
                ->count(),
            'dismantles' => (clone $dismantles)
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
            'dismantles_clear' => (clone $dismantles)
                ->where('status', ReportStatus::CLEAR)
                ->when($userId, fn ($q) => $q->where('cleared_by', $userId))
                ->count(),
            'cctv' => (clone $cctv)
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
            'noc_updates' => (clone $nocUpdates)
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function nocPerformance(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $activationClears = DailyActivation::query()
            ->select('cleared_by', DB::raw('COUNT(*) as total'))
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->where('status', ReportStatus::CLEAR)
            ->whereNotNull('cleared_by')
            ->groupBy('cleared_by')
            ->get()
            ->keyBy('cleared_by');

        $complaintClears = DailyComplaint::query()
            ->select('cleared_by', DB::raw('COUNT(*) as total'))
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->where('status', ReportStatus::CLEAR)
            ->whereNotNull('cleared_by')
            ->groupBy('cleared_by')
            ->get()
            ->keyBy('cleared_by');

        $dismantleClears = DailyDismantle::query()
            ->select('cleared_by', DB::raw('COUNT(*) as total'))
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->where('status', ReportStatus::CLEAR)
            ->whereNotNull('cleared_by')
            ->groupBy('cleared_by')
            ->get()
            ->keyBy('cleared_by');

        $ticketClears = ReportTicket::query()
            ->select('cleared_by', DB::raw('COUNT(*) as total'))
            ->whereNotNull('cleared_by')
            ->whereIn('status', ['Clear', 'Closed'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('cleared_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->orWhere(function ($q2) use ($fromDate, $toDate) {
                        $q2->whereNull('cleared_at')
                            ->whereBetween('closed_at', [$fromDate, $toDate]);
                    });
            })
            ->groupBy('cleared_by')
            ->get()
            ->keyBy('cleared_by');

        $activationInputs = DailyActivation::query()
            ->select('created_by', DB::raw('COUNT(*) as total'))
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->whereNotNull('created_by')
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        $complaintInputs = DailyComplaint::query()
            ->select('created_by', DB::raw('COUNT(*) as total'))
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->whereNotNull('created_by')
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        $dismantleInputs = DailyDismantle::query()
            ->select('created_by', DB::raw('COUNT(*) as total'))
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->whereNotNull('created_by')
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        $userIds = collect([
            $activationClears, $complaintClears, $dismantleClears, $ticketClears,
            $activationInputs, $complaintInputs, $dismantleInputs,
        ])
            ->flatMap(fn ($rows) => $rows->keys())
            ->unique()
            ->filter()
            ->values();

        $users = User::query()->whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

        return $userIds
            ->map(function ($id) use (
                $users,
                $activationClears,
                $complaintClears,
                $dismantleClears,
                $ticketClears,
                $activationInputs,
                $complaintInputs,
                $dismantleInputs,
            ) {
                $activationsClear = (int) ($activationClears->get($id)?->total ?? 0);
                $complaintsClear = (int) ($complaintClears->get($id)?->total ?? 0);
                $dismantlesClear = (int) ($dismantleClears->get($id)?->total ?? 0);
                $ticketsClear = (int) ($ticketClears->get($id)?->total ?? 0);

                return [
                    'user_id' => (int) $id,
                    'name' => $users->get($id)?->name ?? 'User #'.$id,
                    'activations' => (int) ($activationInputs->get($id)?->total ?? 0),
                    'activations_clear' => $activationsClear,
                    'complaints' => (int) ($complaintInputs->get($id)?->total ?? 0),
                    'complaints_clear' => $complaintsClear,
                    'dismantles' => (int) ($dismantleInputs->get($id)?->total ?? 0),
                    'dismantles_clear' => $dismantlesClear,
                    'tickets_clear' => $ticketsClear,
                    'total' => $activationsClear + $complaintsClear + $dismantlesClear + $ticketsClear,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $summary
     * @param  array<int, array<string, mixed>>  $nocPerformance
     * @return array<string, mixed>
     */
    protected function buildCharts(
        string $period,
        Carbon $from,
        Carbon $to,
        ?int $userId,
        array $summary,
        array $nocPerformance,
    ): array {
        $rows = $userId
            ? array_values(array_filter($nocPerformance, fn ($row) => (int) $row['user_id'] === $userId))
            : $nocPerformance;

        $clearByNoc = [
            'categories' => array_map(fn ($row) => (string) $row['name'], $rows),
            'series' => [[
                'name' => 'Total Clear',
                'data' => array_map(fn ($row) => (int) $row['total'], $rows),
                'color' => '#22C55E',
            ]],
        ];

        $clearByType = [
            'categories' => ['Aktivasi', 'Komplain', 'Dismantle'],
            'series' => [[
                'name' => 'Clear',
                'data' => [
                    (int) $summary['activations_clear'],
                    (int) $summary['complaints_clear'],
                    (int) $summary['dismantles_clear'],
                ],
                'color' => '#4F46E5',
            ]],
            'colors' => ['#22C55E', '#EF4444', '#F59E0B'],
        ];

        return [
            'clear_by_noc' => $clearByNoc,
            'trend' => $this->trendChart($period, $from, $to, $userId),
            'clear_by_type' => $clearByType,
        ];
    }

    /** @return array{categories: list<string>, series: list<array{name: string, data: list<int>, color: string}>} */
    protected function trendChart(string $period, Carbon $from, Carbon $to, ?int $userId): array
    {
        [$trendFrom, $trendTo, $bucket] = match ($period) {
            'day' => [$to->copy()->subDays(6)->startOfDay(), $to->copy()->endOfDay(), 'day'],
            'year' => [$from->copy()->startOfMonth(), $to->copy()->endOfMonth(), 'month'],
            default => [$from->copy()->startOfDay(), $to->copy()->endOfDay(), 'day'],
        };

        $categories = [];
        $inputs = [];
        $clears = [];

        if ($bucket === 'month') {
            $cursor = $trendFrom->copy()->startOfMonth();
            while ($cursor->lte($trendTo)) {
                $monthStart = $cursor->copy()->startOfMonth();
                $monthEnd = $cursor->copy()->endOfMonth();
                $categories[] = $cursor->translatedFormat('M Y');
                $inputs[] = $this->countInputsBetween($monthStart, $monthEnd, $userId);
                $clears[] = $this->countClearsBetween($monthStart, $monthEnd, $userId);
                $cursor->addMonth();
            }
        } else {
            $cursor = $trendFrom->copy()->startOfDay();
            while ($cursor->lte($trendTo)) {
                $day = $cursor->copy();
                $categories[] = $day->format('d M');
                $inputs[] = $this->countInputsBetween($day->copy()->startOfDay(), $day->copy()->endOfDay(), $userId);
                $clears[] = $this->countClearsBetween($day->copy()->startOfDay(), $day->copy()->endOfDay(), $userId);
                $cursor->addDay();
            }
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Input', 'data' => $inputs, 'color' => '#6366F1'],
                ['name' => 'Clear', 'data' => $clears, 'color' => '#22C55E'],
            ],
        ];
    }

    protected function countInputsBetween(Carbon $from, Carbon $to, ?int $userId): int
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $count = 0;
        foreach ([DailyActivation::class, DailyComplaint::class, DailyDismantle::class] as $model) {
            $count += $model::query()
                ->whereBetween('report_date', [$fromDate, $toDate])
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count();
        }

        return $count;
    }

    protected function countClearsBetween(Carbon $from, Carbon $to, ?int $userId): int
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $count = 0;
        foreach ([DailyActivation::class, DailyComplaint::class, DailyDismantle::class] as $model) {
            $count += $model::query()
                ->whereBetween('report_date', [$fromDate, $toDate])
                ->where('status', ReportStatus::CLEAR)
                ->when($userId, fn ($q) => $q->where('cleared_by', $userId))
                ->count();
        }

        return $count;
    }

    /** @return array<int, array<string, mixed>> */
    protected function recentActivities(): array
    {
        return ActivityLog::latest()
            ->limit(10)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'message' => ($log->user_name ? $log->user_name.': ' : '').$log->action,
                'time' => $log->created_at?->diffForHumans() ?? '—',
                'severity' => match ($log->type) {
                    'login', 'backup' => 'success',
                    default => 'info',
                },
            ])
            ->all();
    }
}
