<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DailyActivation;
use App\Models\DailyCctvSetup;
use App\Models\DailyComplaint;
use App\Models\DailyNocUpdate;
use App\Models\Dismantle;
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
                'dismantles' => Dismantle::whereIn('status', ['Pending', 'On-Progress'])->count(),
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $period = $request->string('period', 'day')->toString();
        if (! in_array($period, ['day', 'week', 'month', 'year', 'custom'], true)) {
            $period = 'day';
        }

        $anchor = Carbon::parse($request->string('date', now()->toDateString())->toString())
            ->timezone(config('app.timezone'));

        if ($period === 'custom') {
            $from = Carbon::parse($request->string('from', $anchor->toDateString())->toString())
                ->timezone(config('app.timezone'))
                ->startOfDay();
            $to = Carbon::parse($request->string('to', $from->toDateString())->toString())
                ->timezone(config('app.timezone'))
                ->endOfDay();
            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
        } else {
            [$from, $to] = $this->periodRange($period, $anchor);
        }

        $userId = $request->integer('user_id', 0) ?: null;
        $odcName = trim($request->string('odc_name')->toString()) ?: null;
        $complaintOdcName = trim($request->string('complaint_odc_name')->toString()) ?: null;
        $clientShareSource = strtolower(trim($request->string('client_share_source')->toString()));
        if (! in_array($clientShareSource, ['all', 'complaint', 'ticket'], true)) {
            $clientShareSource = 'all';
        }
        $periodDays = max(1, $from->diffInDays($to) + 1);

        $summary = $this->summaryForRange($from, $to, $userId, $odcName);
        $nocPerformance = $this->nocPerformance($from, $to, $periodDays, $odcName);
        if ($userId) {
            $nocPerformance = array_values(array_filter(
                $nocPerformance,
                fn ($row) => (int) $row['user_id'] === $userId,
            ));
        }

        $categoryKpis = $this->categoryKpis($summary, $nocPerformance);
        $specialists = $this->specialistBadges($nocPerformance);
        $charts = $this->buildCharts($summary, $nocPerformance);
        $odcStats = $this->odcPerformance($from, $to, $periodDays, $odcName);
        $complaintClientShare = $this->complaintClientShare($from, $to, $complaintOdcName, $clientShareSource);

        return response()->json([
            'period' => [
                'type' => $period,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $period === 'custom'
                    ? $from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y')
                    : $this->periodLabel($period, $from, $to),
                'days' => $periodDays,
            ],
            'summary' => $summary,
            'category_kpis' => $categoryKpis,
            'kpis' => $categoryKpis,
            'specialists' => $specialists,
            'noc_performance' => $nocPerformance,
            'odc_stats' => $odcStats,
            'complaint_client_share' => $complaintClientShare,
            'charts' => $charts,
            'heatmap' => $this->weeklyHeatmap($to, $userId, $odcName),
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
                'dismantles' => Dismantle::whereIn('status', ['Pending', 'On-Progress'])->count(),
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
    protected function summaryForRange(Carbon $from, Carbon $to, ?int $userId, ?string $odcName = null): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $activations = DailyActivation::query()
            ->whereBetween('report_date', [$fromDate, $toDate]);
        // Aktivasi belum punya odc_name — jika filter ODC aktif, anggap 0
        if ($odcName) {
            $activations->whereRaw('1 = 0');
        }

        $complaints = DailyComplaint::query()
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->when($odcName, fn ($q) => $q->where('odc_name', $odcName));

        $cctv = DailyCctvSetup::query()
            ->whereBetween('report_date', [$fromDate, $toDate]);
        if ($odcName) {
            $cctv->whereRaw('1 = 0');
        }

        $nocUpdates = DailyNocUpdate::query()
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->when($odcName, fn ($q) => $q->where('odc_name', $odcName));

        $tickets = ReportTicket::query()
            ->where(function ($q) use ($from, $to, $fromDate, $toDate) {
                $q->whereBetween('opened_at', [$fromDate, $toDate])
                    ->orWhereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
            })
            ->when($odcName, fn ($q) => $q->where('odc_name', $odcName));

        return [
            'activations' => (clone $activations)
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
            'activations_open' => $odcName
                ? 0
                : DailyActivation::query()
                    ->whereDate('report_date', '<=', $toDate)
                    ->where(function ($q) {
                        $q->whereNull('status')
                            ->orWhereRaw('LOWER(status) <> ?', [strtolower(ReportStatus::CLEAR)]);
                    })
                    ->when($userId, fn ($q) => $q->where('created_by', $userId))
                    ->count(),
            'activations_clear' => $odcName
                ? 0
                : $this->countClearsInRange(DailyActivation::class, $from, $to, $userId),
            'complaints' => (clone $complaints)
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
            'complaints_open' => DailyComplaint::query()
                ->whereDate('report_date', '<=', $toDate)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw('LOWER(status) <> ?', [strtolower(ReportStatus::CLEAR)]);
                })
                ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
            'complaints_clear' => $this->countClearsInRange(DailyComplaint::class, $from, $to, $userId, $odcName),
            'dismantles' => $this->countDismantleOpenedInRange($from, $to, $userId, $odcName),
            'dismantles_open' => $this->countDismantleOpens($toDate, $userId, $odcName),
            'dismantles_clear' => $this->countDismantleClears($from, $to, $userId, $odcName),
            'cctv' => (clone $cctv)
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
            'cctv_open' => $odcName
                ? 0
                : $this->countOpensUpTo(DailyCctvSetup::class, $toDate, $userId),
            'cctv_clear' => $odcName
                ? 0
                : $this->countClearsInRange(DailyCctvSetup::class, $from, $to, $userId),
            'tickets' => (clone $tickets)
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
            'tickets_open' => ReportTicket::query()
                ->where('status', 'On-Progress')
                ->where(function ($q) use ($to, $toDate) {
                    $q->whereDate('opened_at', '<=', $toDate)
                        ->orWhere('created_at', '<=', $to->copy()->endOfDay());
                })
                ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
            'tickets_clear' => ReportTicket::query()
                ->whereNotNull('cleared_by')
                ->whereIn('status', ['Clear', 'Closed'])
                ->where(function ($q) use ($from, $to, $fromDate, $toDate) {
                    $q->whereBetween('cleared_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                        ->orWhere(function ($q2) use ($fromDate, $toDate) {
                            $q2->whereNull('cleared_at')
                                ->whereBetween('closed_at', [$fromDate, $toDate]);
                        });
                })
                ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
                ->when($userId, fn ($q) => $q->where('cleared_by', $userId))
                ->count(),
            'noc_updates' => (clone $nocUpdates)
                ->when($userId, fn ($q) => $q->where('created_by', $userId))
                ->count(),
            'noc_updates_open' => $this->countOpensUpTo(DailyNocUpdate::class, $toDate, $userId, $odcName),
            'noc_updates_clear' => $this->countClearsInRange(DailyNocUpdate::class, $from, $to, $userId, $odcName),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function nocPerformance(Carbon $from, Carbon $to, int $periodDays = 1, ?string $odcName = null): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $activationClears = $odcName
            ? collect()
            : $this->groupClears(DailyActivation::class, $fromDate, $toDate);
        $complaintClears = $this->groupClears(DailyComplaint::class, $fromDate, $toDate, $odcName);
        $dismantleClears = $this->groupDismantleClears($fromDate, $toDate, $odcName);
        $cctvClears = $odcName
            ? collect()
            : $this->groupClears(DailyCctvSetup::class, $fromDate, $toDate);
        $cctvInputs = $odcName
            ? collect()
            : $this->groupInputs(DailyCctvSetup::class, $fromDate, $toDate);
        $nocClears = $this->groupClears(DailyNocUpdate::class, $fromDate, $toDate, $odcName);

        $activationOpens = $odcName
            ? collect()
            : $this->groupOpens(DailyActivation::class, $fromDate, $toDate);
        $complaintOpens = $this->groupOpens(DailyComplaint::class, $fromDate, $toDate, $odcName);
        $dismantleOpens = $this->groupDismantleOpens($toDate, $odcName);
        $cctvOpens = $odcName
            ? collect()
            : $this->groupOpens(DailyCctvSetup::class, $fromDate, $toDate);
        $nocOpens = $this->groupOpens(DailyNocUpdate::class, $fromDate, $toDate, $odcName);

        $ticketClears = ReportTicket::query()
            ->select('cleared_by', DB::raw('COUNT(*) as total'))
            ->whereNotNull('cleared_by')
            ->whereIn('status', ['Clear', 'Closed'])
            ->where(function ($q) use ($from, $to, $fromDate, $toDate) {
                $q->whereBetween('cleared_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->orWhere(function ($q2) use ($fromDate, $toDate) {
                        $q2->whereNull('cleared_at')
                            ->whereBetween('closed_at', [$fromDate, $toDate]);
                    });
            })
            ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
            ->groupBy('cleared_by')
            ->get()
            ->keyBy('cleared_by');

        $ticketOpens = ReportTicket::query()
            ->select('created_by', DB::raw('COUNT(*) as total'))
            ->whereNotNull('created_by')
            ->where('status', 'On-Progress')
            ->where(function ($q) use ($to, $toDate) {
                $q->whereDate('opened_at', '<=', $toDate)
                    ->orWhere('created_at', '<=', $to->copy()->endOfDay());
            })
            ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        $activationInputs = $odcName
            ? collect()
            : $this->groupInputs(DailyActivation::class, $fromDate, $toDate);
        $complaintInputs = $this->groupInputs(DailyComplaint::class, $fromDate, $toDate, $odcName);
        $dismantleInputs = $this->groupDismantleInputs($fromDate, $toDate, $odcName);

        $userIds = collect([
            $activationClears, $complaintClears, $dismantleClears, $ticketClears, $cctvClears, $cctvInputs, $nocClears,
            $activationInputs, $complaintInputs, $dismantleInputs,
            $activationOpens, $complaintOpens, $ticketOpens, $dismantleOpens, $cctvOpens, $nocOpens,
        ])
            ->flatMap(fn ($rows) => $rows->keys())
            ->unique()
            ->filter()
            ->values();

        $users = User::query()->whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

        $rows = $userIds
            ->map(function ($id) use (
                $users,
                $activationClears,
                $complaintClears,
                $dismantleClears,
                $ticketClears,
                $ticketOpens,
                $cctvClears,
                $cctvInputs,
                $nocClears,
                $activationInputs,
                $complaintInputs,
                $dismantleInputs,
                $activationOpens,
                $complaintOpens,
                $dismantleOpens,
                $cctvOpens,
                $nocOpens,
                $periodDays,
            ) {
                $activationsClear = (int) ($activationClears->get($id)?->total ?? 0);
                $complaintsClear = (int) ($complaintClears->get($id)?->total ?? 0);
                $dismantlesClear = (int) ($dismantleClears->get($id)?->total ?? 0);
                $ticketsClear = (int) ($ticketClears->get($id)?->total ?? 0);
                $activationsOpen = (int) ($activationOpens->get($id)?->total ?? 0);
                $complaintsOpen = (int) ($complaintOpens->get($id)?->total ?? 0);
                $ticketsOpen = (int) ($ticketOpens->get($id)?->total ?? 0);
                $dismantlesOpen = (int) ($dismantleOpens->get($id)?->total ?? 0);
                $cctvOpen = (int) ($cctvOpens->get($id)?->total ?? 0);
                $cctvClear = (int) ($cctvClears->get($id)?->total ?? 0);
                $nocClear = (int) ($nocClears->get($id)?->total ?? 0);
                $nocOpen = (int) ($nocOpens->get($id)?->total ?? 0);
                $cctv = max($cctvClear, (int) ($cctvInputs->get($id)?->total ?? 0));
                $total = $activationsClear + $complaintsClear + $dismantlesClear + $ticketsClear + $cctvClear + $nocClear;

                return [
                    'user_id' => (int) $id,
                    'name' => $users->get($id)?->name ?? 'User #'.$id,
                    'activations' => (int) ($activationInputs->get($id)?->total ?? 0),
                    'activations_open' => $activationsOpen,
                    'activations_clear' => $activationsClear,
                    'complaints' => (int) ($complaintInputs->get($id)?->total ?? 0),
                    'complaints_open' => $complaintsOpen,
                    'complaints_clear' => $complaintsClear,
                    'dismantles' => (int) ($dismantleInputs->get($id)?->total ?? 0),
                    'dismantles_open' => $dismantlesOpen,
                    'dismantles_clear' => $dismantlesClear,
                    'tickets_open' => $ticketsOpen,
                    'tickets_clear' => $ticketsClear,
                    'cctv' => $cctv,
                    'cctv_open' => $cctvOpen,
                    'cctv_clear' => $cctvClear,
                    'noc_updates_open' => $nocOpen,
                    'noc_updates_clear' => $nocClear,
                    'total' => $total,
                    'avg_per_day' => round($total / max(1, $periodDays), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $grandTotal = max(1, (int) $rows->sum('total'));

        return $rows
            ->map(function (array $row) use ($grandTotal) {
                $row['contribution_pct'] = round(($row['total'] / $grandTotal) * 100, 1);

                return $row;
            })
            ->all();
    }

    /**
     * Statistik per ODC (Komplain, Ticket, Update NOC, Dismantle).
     * Aktivasi/CCTV belum punya odc_name → selalu 0.
     * Group by kolom mentah (kompatibel ONLY_FULL_GROUP_BY), normalisasi nama di PHP.
     *
     * @return list<array<string, mixed>>
     */
    protected function odcPerformance(Carbon $from, Carbon $to, int $periodDays = 1, ?string $odcName = null): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $fromStart = $from->copy()->startOfDay();
        $toEnd = $to->copy()->endOfDay();

        $complaintClears = $this->mergeOdcCounts(
            DailyComplaint::query()
                ->selectRaw('odc_name, COUNT(*) as total')
                ->where('status', ReportStatus::CLEAR)
                ->where(function ($q) use ($fromStart, $toEnd, $fromDate, $toDate) {
                    $q->whereBetween('cleared_at', [$fromStart, $toEnd])
                        ->orWhere(function ($q2) use ($fromDate, $toDate) {
                            $q2->whereNull('cleared_at')
                                ->whereBetween('report_date', [$fromDate, $toDate]);
                        });
                })
                ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
                ->groupBy('odc_name')
                ->get(),
            'odc_name',
        );

        $complaintOpens = $this->mergeOdcCounts(
            DailyComplaint::query()
                ->selectRaw('odc_name, COUNT(*) as total')
                ->whereDate('report_date', '<=', $toDate)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw('LOWER(status) <> ?', [strtolower(ReportStatus::CLEAR)]);
                })
                ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
                ->groupBy('odc_name')
                ->get(),
            'odc_name',
        );

        $ticketClears = $this->mergeOdcCounts(
            ReportTicket::query()
                ->selectRaw('odc_name, COUNT(*) as total')
                ->whereIn('status', ['Clear', 'Closed'])
                ->where(function ($q) use ($fromStart, $toEnd, $fromDate, $toDate) {
                    $q->whereBetween('cleared_at', [$fromStart, $toEnd])
                        ->orWhere(function ($q2) use ($fromDate, $toDate) {
                            $q2->whereNull('cleared_at')
                                ->whereBetween('closed_at', [$fromDate, $toDate]);
                        });
                })
                ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
                ->groupBy('odc_name')
                ->get(),
            'odc_name',
        );

        $ticketOpens = $this->mergeOdcCounts(
            ReportTicket::query()
                ->selectRaw('odc_name, COUNT(*) as total')
                ->where('status', 'On-Progress')
                ->where(function ($q) use ($to, $toDate) {
                    $q->whereDate('opened_at', '<=', $toDate)
                        ->orWhere('created_at', '<=', $to->copy()->endOfDay());
                })
                ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
                ->groupBy('odc_name')
                ->get(),
            'odc_name',
        );

        $nocClears = $this->mergeOdcCounts(
            DailyNocUpdate::query()
                ->selectRaw('odc_name, COUNT(*) as total')
                ->where('status', ReportStatus::CLEAR)
                ->where(function ($q) use ($fromStart, $toEnd, $fromDate, $toDate) {
                    $q->whereBetween('cleared_at', [$fromStart, $toEnd])
                        ->orWhere(function ($q2) use ($fromDate, $toDate) {
                            $q2->whereNull('cleared_at')
                                ->whereBetween('report_date', [$fromDate, $toDate]);
                        });
                })
                ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
                ->groupBy('odc_name')
                ->get(),
            'odc_name',
        );

        $nocOpens = $this->mergeOdcCounts(
            DailyNocUpdate::query()
                ->selectRaw('odc_name, COUNT(*) as total')
                ->whereDate('report_date', '<=', $toDate)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw('LOWER(status) <> ?', [strtolower(ReportStatus::CLEAR)]);
                })
                ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
                ->groupBy('odc_name')
                ->get(),
            'odc_name',
        );

        $dismantleClears = $this->mergeOdcCounts(
            Dismantle::query()
                ->selectRaw('location, COUNT(*) as total')
                ->where('status', 'Clear')
                ->where(function ($q) use ($fromDate, $toDate, $fromStart, $toEnd) {
                    $q->whereBetween('closed_at', [$fromDate, $toDate])
                        ->orWhere(function ($q2) use ($fromStart, $toEnd) {
                            $q2->whereNull('closed_at')
                                ->whereBetween('updated_at', [$fromStart, $toEnd]);
                        });
                })
                ->when($odcName, fn ($q) => $this->scopeDismantleByOdc($q, $odcName))
                ->groupBy('location')
                ->get(),
            'location',
        );

        $dismantleOpens = $this->mergeOdcCounts(
            Dismantle::query()
                ->selectRaw('location, COUNT(*) as total')
                ->whereIn('status', ['Pending', 'On-Progress'])
                ->where(function ($q) use ($toDate) {
                    $q->whereDate('opened_at', '<=', $toDate)
                        ->orWhere(function ($q2) use ($toDate) {
                            $q2->whereNull('opened_at')
                                ->whereDate('created_at', '<=', $toDate);
                        });
                })
                ->when($odcName, fn ($q) => $this->scopeDismantleByOdc($q, $odcName))
                ->groupBy('location')
                ->get(),
            'location',
        );

        $odcKeys = collect([
            $complaintClears, $complaintOpens, $ticketClears, $ticketOpens,
            $nocClears, $nocOpens, $dismantleClears, $dismantleOpens,
        ])
            ->flatMap(fn ($rows) => $rows->keys())
            ->unique()
            ->filter()
            ->values();

        $rows = $odcKeys
            ->map(function ($key) use (
                $complaintClears,
                $complaintOpens,
                $ticketClears,
                $ticketOpens,
                $nocClears,
                $nocOpens,
                $dismantleClears,
                $dismantleOpens,
                $periodDays,
            ) {
                $complaintsClear = (int) ($complaintClears->get($key) ?? 0);
                $complaintsOpen = (int) ($complaintOpens->get($key) ?? 0);
                $ticketsClear = (int) ($ticketClears->get($key) ?? 0);
                $ticketsOpen = (int) ($ticketOpens->get($key) ?? 0);
                $nocClear = (int) ($nocClears->get($key) ?? 0);
                $nocOpen = (int) ($nocOpens->get($key) ?? 0);
                $dismantlesClear = (int) ($dismantleClears->get($key) ?? 0);
                $dismantlesOpen = (int) ($dismantleOpens->get($key) ?? 0);
                $total = $complaintsClear + $ticketsClear + $nocClear + $dismantlesClear;

                return [
                    'odc_name' => (string) $key,
                    'complaints_open' => $complaintsOpen,
                    'complaints_clear' => $complaintsClear,
                    'activations_open' => 0,
                    'activations_clear' => 0,
                    'tickets_open' => $ticketsOpen,
                    'tickets_clear' => $ticketsClear,
                    'cctv_clear' => 0,
                    'dismantles_open' => $dismantlesOpen,
                    'dismantles_clear' => $dismantlesClear,
                    'noc_updates_open' => $nocOpen,
                    'noc_updates_clear' => $nocClear,
                    'total' => $total,
                    'avg_per_day' => round($total / max(1, $periodDays), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $grandTotal = max(1, (int) $rows->sum('total'));

        return $rows
            ->map(function (array $row) use ($grandTotal) {
                $row['contribution_pct'] = round(($row['total'] / $grandTotal) * 100, 1);

                return $row;
            })
            ->all();
    }

    /**
     * Persentase kontribusi client (top 10) dari komplain dan/atau tiket.
     * Filter ODC & source hanya memengaruhi ranking ini.
     *
     * @param  'all'|'complaint'|'ticket'  $source
     * @return array{total: int, complaints_total: int, tickets_total: int, source: string, rows: list<array<string, mixed>>}
     */
    protected function complaintClientShare(
        Carbon $from,
        Carbon $to,
        ?string $odcName = null,
        string $source = 'all',
    ): array {
        $byClient = [];

        $bump = function (
            string $key,
            string $name,
            ?string $code,
            ?string $odc,
            bool $isGamas,
            string $type,
        ) use (&$byClient): void {
            if (! isset($byClient[$key])) {
                $byClient[$key] = [
                    'key' => $key,
                    'name' => $name,
                    'customer_code' => $code,
                    'odc_name' => $odc,
                    'is_gamas' => $isGamas,
                    'complaints_count' => 0,
                    'tickets_count' => 0,
                ];
            }
            if ($type === 'complaint') {
                $byClient[$key]['complaints_count']++;
            } else {
                $byClient[$key]['tickets_count']++;
            }
            if (! $byClient[$key]['odc_name'] && $odc) {
                $byClient[$key]['odc_name'] = $odc;
            }
            if (! $byClient[$key]['customer_code'] && $code) {
                $byClient[$key]['customer_code'] = $code;
            }
        };

        if ($source === 'all' || $source === 'complaint') {
            $complaints = DailyComplaint::query()
                ->select([
                    'complaint_type',
                    'customer_id',
                    'customer_code',
                    'customer_name',
                    'location_label',
                    'odc_name',
                ])
                ->whereBetween('report_date', [$from->toDateString(), $to->toDateString()])
                ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
                ->get();

            foreach ($complaints as $row) {
                $isGamas = ($row->complaint_type ?? '') === DailyComplaint::TYPE_GAMAS;
                if ($isGamas) {
                    $label = trim((string) ($row->location_label ?: $row->customer_name ?: 'Gamas'));
                    $key = 'gamas:'.mb_strtolower($label).'|'.mb_strtolower((string) ($row->odc_name ?? ''));
                    $code = null;
                    $name = 'Gamas: '.$label;
                } else {
                    $code = trim((string) ($row->customer_code ?? ''));
                    $name = trim((string) ($row->customer_name ?? ''));
                    if ($code === '' && $name === '' && ! $row->customer_id) {
                        continue;
                    }
                    $key = $code !== ''
                        ? 'code:'.mb_strtolower($code)
                        : ($row->customer_id ? 'id:'.$row->customer_id : 'name:'.mb_strtolower($name));
                    $name = $name !== '' ? $name : ($code !== '' ? $code : 'Pelanggan');
                }

                $bump(
                    $key,
                    $name,
                    $code,
                    $row->odc_name ? (string) $row->odc_name : null,
                    $isGamas,
                    'complaint',
                );
            }
        }

        if ($source === 'all' || $source === 'ticket') {
            $fromDate = $from->toDateString();
            $toDate = $to->toDateString();
            $tickets = ReportTicket::query()
                ->select(['customer_code', 'customer_name', 'odc_name', 'location'])
                ->where(function ($q) use ($from, $to, $fromDate, $toDate) {
                    $q->whereBetween('opened_at', [$fromDate, $toDate])
                        ->orWhere(function ($q2) use ($from, $to) {
                            $q2->whereNull('opened_at')
                                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
                        });
                })
                ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
                ->get();

            foreach ($tickets as $row) {
                $code = trim((string) ($row->customer_code ?? ''));
                $name = trim((string) ($row->customer_name ?? ''));
                if ($code === '' && $name === '') {
                    continue;
                }
                $key = $code !== ''
                    ? 'code:'.mb_strtolower($code)
                    : 'name:'.mb_strtolower($name);
                $name = $name !== '' ? $name : $code;

                $bump(
                    $key,
                    $name,
                    $code !== '' ? $code : null,
                    $row->odc_name ? (string) $row->odc_name : null,
                    false,
                    'ticket',
                );
            }
        }

        $complaintsTotal = (int) array_sum(array_column($byClient, 'complaints_count'));
        $ticketsTotal = (int) array_sum(array_column($byClient, 'tickets_count'));

        foreach ($byClient as &$client) {
            $client['count'] = match ($source) {
                'complaint' => (int) $client['complaints_count'],
                'ticket' => (int) $client['tickets_count'],
                default => (int) $client['complaints_count'] + (int) $client['tickets_count'],
            };
        }
        unset($client);

        $byClient = array_filter($byClient, fn (array $c) => (int) $c['count'] > 0);
        $actualTotal = (int) array_sum(array_column($byClient, 'count'));
        $total = max(1, $actualTotal);

        $rows = collect($byClient)
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->map(function (array $client) use ($total) {
                $count = (int) $client['count'];

                return [
                    'key' => $client['key'],
                    'name' => $client['name'],
                    'customer_code' => $client['customer_code'],
                    'odc_name' => $client['odc_name'],
                    'is_gamas' => $client['is_gamas'],
                    'complaints_count' => (int) $client['complaints_count'],
                    'tickets_count' => (int) $client['tickets_count'],
                    'count' => $count,
                    'pct' => round(($count / $total) * 100, 1),
                ];
            })
            ->all();

        return [
            'total' => $actualTotal,
            'complaints_total' => $complaintsTotal,
            'tickets_total' => $ticketsTotal,
            'source' => $source,
            'rows' => $rows,
        ];
    }

    /**
     * Gabungkan hasil GROUP BY kolom mentah → key ODC ternormalisasi.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<string, int>
     */
    protected function mergeOdcCounts($rows, string $column): \Illuminate\Support\Collection
    {
        $merged = [];

        foreach ($rows as $row) {
            $raw = trim((string) ($row->{$column} ?? ''));
            $key = $raw !== '' ? $raw : 'Tanpa ODC';
            $merged[$key] = ($merged[$key] ?? 0) + (int) ($row->total ?? 0);
        }

        return collect($merged);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Dismantle>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Dismantle>
     */
    protected function scopeDismantleByOdc($query, string $odcName)
    {
        return $query->where(function ($q) use ($odcName) {
            $q->where('location', $odcName)
                ->orWhere('area', $odcName)
                ->orWhere('location', 'like', '%'.$odcName.'%')
                ->orWhere('area', 'like', '%'.$odcName.'%');
        });
    }

    protected function countDismantleOpenedInRange(
        Carbon $from,
        Carbon $to,
        ?int $userId = null,
        ?string $odcName = null,
    ): int {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        return Dismantle::query()
            ->where(function ($q) use ($from, $to, $fromDate, $toDate) {
                $q->whereBetween('opened_at', [$fromDate, $toDate])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('opened_at')
                            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
                    });
            })
            ->when($userId, fn ($q) => $q->where(function ($q2) use ($userId) {
                $q2->where('created_by', $userId)->orWhere('assigned_to', $userId);
            }))
            ->when($odcName, fn ($q) => $this->scopeDismantleByOdc($q, $odcName))
            ->count();
    }

    protected function countDismantleOpens(
        string $toDate,
        ?int $userId = null,
        ?string $odcName = null,
    ): int {
        return Dismantle::query()
            ->whereIn('status', ['Pending', 'On-Progress'])
            ->where(function ($q) use ($toDate) {
                $q->whereDate('opened_at', '<=', $toDate)
                    ->orWhere(function ($q2) use ($toDate) {
                        $q2->whereNull('opened_at')
                            ->whereDate('created_at', '<=', $toDate);
                    });
            })
            ->when($userId, fn ($q) => $q->where(function ($q2) use ($userId) {
                $q2->where('created_by', $userId)->orWhere('assigned_to', $userId);
            }))
            ->when($odcName, fn ($q) => $this->scopeDismantleByOdc($q, $odcName))
            ->count();
    }

    protected function countDismantleClears(
        Carbon $from,
        Carbon $to,
        ?int $userId = null,
        ?string $odcName = null,
    ): int {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        return Dismantle::query()
            ->where('status', 'Clear')
            ->where(function ($q) use ($from, $to, $fromDate, $toDate) {
                $q->whereBetween('closed_at', [$fromDate, $toDate])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('closed_at')
                            ->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
                    })
                    ->orWhere(function ($q2) use ($fromDate, $toDate) {
                        $q2->whereNull('closed_at')
                            ->whereNull('completed_at')
                            ->whereBetween('opened_at', [$fromDate, $toDate]);
                    });
            })
            ->when($userId, fn ($q) => $q->where(function ($q2) use ($userId) {
                $q2->where('assigned_to', $userId)->orWhere('created_by', $userId);
            }))
            ->when($odcName, fn ($q) => $this->scopeDismantleByOdc($q, $odcName))
            ->count();
    }

    protected function groupDismantleClears(string $fromDate, string $toDate, ?string $odcName = null)
    {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        return Dismantle::query()
            ->select(DB::raw('COALESCE(assigned_to, created_by) as cleared_by'), DB::raw('COUNT(*) as total'))
            ->where('status', 'Clear')
            ->where(function ($q) use ($from, $to, $fromDate, $toDate) {
                $q->whereBetween('closed_at', [$fromDate, $toDate])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('closed_at')
                            ->whereBetween('completed_at', [$from, $to]);
                    })
                    ->orWhere(function ($q2) use ($fromDate, $toDate) {
                        $q2->whereNull('closed_at')
                            ->whereNull('completed_at')
                            ->whereBetween('opened_at', [$fromDate, $toDate]);
                    });
            })
            ->where(function ($q) {
                $q->whereNotNull('assigned_to')->orWhereNotNull('created_by');
            })
            ->when($odcName, fn ($q) => $this->scopeDismantleByOdc($q, $odcName))
            ->groupBy(DB::raw('COALESCE(assigned_to, created_by)'))
            ->get()
            ->keyBy('cleared_by');
    }

    protected function groupDismantleOpens(string $toDate, ?string $odcName = null)
    {
        return Dismantle::query()
            ->select(DB::raw('COALESCE(assigned_to, created_by) as created_by'), DB::raw('COUNT(*) as total'))
            ->whereIn('status', ['Pending', 'On-Progress'])
            ->where(function ($q) use ($toDate) {
                $q->whereDate('opened_at', '<=', $toDate)
                    ->orWhere(function ($q2) use ($toDate) {
                        $q2->whereNull('opened_at')
                            ->whereDate('created_at', '<=', $toDate);
                    });
            })
            ->where(function ($q) {
                $q->whereNotNull('assigned_to')->orWhereNotNull('created_by');
            })
            ->when($odcName, fn ($q) => $this->scopeDismantleByOdc($q, $odcName))
            ->groupBy(DB::raw('COALESCE(assigned_to, created_by)'))
            ->get()
            ->keyBy('created_by');
    }

    protected function groupDismantleInputs(string $fromDate, string $toDate, ?string $odcName = null)
    {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        return Dismantle::query()
            ->select('created_by', DB::raw('COUNT(*) as total'))
            ->whereNotNull('created_by')
            ->where(function ($q) use ($from, $to, $fromDate, $toDate) {
                $q->whereBetween('opened_at', [$fromDate, $toDate])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('opened_at')
                            ->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->when($odcName, fn ($q) => $this->scopeDismantleByOdc($q, $odcName))
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');
    }

    /**
     * On-Progress termasuk backlog (report_date ≤ sampai).
     *
     * @param  class-string  $model
     */
    protected function countOpensUpTo(
        string $model,
        string $toDate,
        ?int $userId = null,
        ?string $odcName = null,
    ): int {
        return $model::query()
            ->whereDate('report_date', '<=', $toDate)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(status) <> ?', [strtolower(ReportStatus::CLEAR)]);
            })
            ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
            ->when($userId, fn ($q) => $q->where('created_by', $userId))
            ->count();
    }

    /**
     * Clear dihitung dari tanggal penyelesaian (cleared_at), bukan report_date.
     * Fallback: data lama tanpa cleared_at tetap pakai report_date.
     *
     * @param  class-string  $model
     */
    protected function countClearsInRange(
        string $model,
        Carbon $from,
        Carbon $to,
        ?int $userId = null,
        ?string $odcName = null,
    ): int {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        return $model::query()
            ->where('status', ReportStatus::CLEAR)
            ->where(function ($q) use ($from, $to, $fromDate, $toDate) {
                $q->whereBetween('cleared_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->orWhere(function ($q2) use ($fromDate, $toDate) {
                        $q2->whereNull('cleared_at')
                            ->whereBetween('report_date', [$fromDate, $toDate]);
                    });
            })
            ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
            ->when($userId, fn ($q) => $q->where('cleared_by', $userId))
            ->count();
    }

    /** @param  class-string  $model */
    protected function groupClears(string $model, string $fromDate, string $toDate, ?string $odcName = null)
    {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        return $model::query()
            ->select('cleared_by', DB::raw('COUNT(*) as total'))
            ->where('status', ReportStatus::CLEAR)
            ->whereNotNull('cleared_by')
            ->where(function ($q) use ($from, $to, $fromDate, $toDate) {
                $q->whereBetween('cleared_at', [$from, $to])
                    ->orWhere(function ($q2) use ($fromDate, $toDate) {
                        $q2->whereNull('cleared_at')
                            ->whereBetween('report_date', [$fromDate, $toDate]);
                    });
            })
            ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
            ->groupBy('cleared_by')
            ->get()
            ->keyBy('cleared_by');
    }

    /** Item masih On-Progress (termasuk dari hari sebelumnya), diatribusikan ke yang input.
     *
     * @param  class-string  $model
     */
    protected function groupOpens(string $model, string $fromDate, string $toDate, ?string $odcName = null)
    {
        return $model::query()
            ->select('created_by', DB::raw('COUNT(*) as total'))
            ->whereDate('report_date', '<=', $toDate)
            ->whereNotNull('created_by')
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(status) <> ?', [strtolower(ReportStatus::CLEAR)]);
            })
            ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');
    }

    /** @param  class-string  $model */
    protected function groupInputs(string $model, string $fromDate, string $toDate, ?string $odcName = null)
    {
        return $model::query()
            ->select('created_by', DB::raw('COUNT(*) as total'))
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->whereNotNull('created_by')
            ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');
    }

    /**
     * @param  array<string, int>  $summary
     * @param  array<int, array<string, mixed>>  $nocPerformance
     * @return array<int, array<string, mixed>>
     */
    protected function categoryKpis(array $summary, array $nocPerformance): array
    {
        $top = function (string $field) use ($nocPerformance): ?array {
            $best = collect($nocPerformance)->sortByDesc($field)->first();
            if (! $best || (int) ($best[$field] ?? 0) <= 0) {
                return null;
            }

            return [
                'user_id' => (int) $best['user_id'],
                'name' => (string) $best['name'],
                'count' => (int) $best[$field],
            ];
        };

        return [
            [
                'key' => 'complaints',
                'label' => 'Komplain',
                'value' => (int) $summary['complaints'],
                'open' => (int) ($summary['complaints_open'] ?? 0),
                'clear' => (int) $summary['complaints_clear'],
                'split_status' => true,
                'color' => 'danger',
                'icon' => 'ticket',
                'top' => $top('complaints_clear'),
            ],
            [
                'key' => 'activations',
                'label' => 'Aktivasi',
                'value' => (int) $summary['activations'],
                'open' => (int) ($summary['activations_open'] ?? 0),
                'clear' => (int) $summary['activations_clear'],
                'split_status' => true,
                'color' => 'success',
                'icon' => 'activation',
                'top' => $top('activations_clear'),
            ],
            [
                'key' => 'tickets',
                'label' => 'Ticket',
                'value' => (int) ($summary['tickets'] ?? 0),
                'open' => (int) ($summary['tickets_open'] ?? 0),
                'clear' => (int) ($summary['tickets_clear'] ?? 0),
                'split_status' => true,
                'color' => 'info',
                'icon' => 'ticket',
                'top' => $top('tickets_clear'),
            ],
            [
                'key' => 'dismantles',
                'label' => 'Dismantle',
                'value' => (int) $summary['dismantles'],
                'open' => (int) ($summary['dismantles_open'] ?? 0),
                'clear' => (int) ($summary['dismantles_clear'] ?? 0),
                'split_status' => true,
                'color' => 'warning',
                'icon' => 'dismantle',
                'top' => $top('dismantles_clear'),
            ],
            [
                'key' => 'cctv',
                'label' => 'CCTV',
                'value' => (int) $summary['cctv'],
                'open' => (int) ($summary['cctv_open'] ?? 0),
                'clear' => (int) ($summary['cctv_clear'] ?? 0),
                'split_status' => true,
                'color' => 'primary',
                'icon' => 'cctv',
                'top' => $top('cctv_clear') ?? $top('cctv'),
            ],
            [
                'key' => 'noc_updates',
                'label' => 'Update NOC',
                'value' => (int) ($summary['noc_updates'] ?? 0),
                'open' => (int) ($summary['noc_updates_open'] ?? 0),
                'clear' => (int) ($summary['noc_updates_clear'] ?? 0),
                'split_status' => true,
                'color' => 'info',
                'icon' => 'noc',
                'top' => $top('noc_updates_clear'),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nocPerformance
     * @return array<int, array<string, mixed>>
     */
    protected function specialistBadges(array $nocPerformance): array
    {
        $pick = function (string $field, string $title, string $emoji, string $color) use ($nocPerformance): ?array {
            $best = collect($nocPerformance)->sortByDesc($field)->first();
            if (! $best || (int) ($best[$field] ?? 0) <= 0) {
                return null;
            }

            return [
                'key' => $field,
                'title' => $title,
                'emoji' => $emoji,
                'color' => $color,
                'name' => (string) $best['name'],
                'count' => (int) $best[$field],
                'unit' => str_contains($field, 'activation') ? 'Aktivasi'
                    : (str_contains($field, 'complaint') ? 'Clear'
                    : (str_contains($field, 'ticket') ? 'Ticket'
                    : (str_contains($field, 'cctv') ? 'CCTV' : 'Dismantle'))),
            ];
        };

        return array_values(array_filter([
            $pick('complaints_clear', 'King of Komplain', '👑', 'danger'),
            $pick('activations_clear', 'Aktivator Terbaik', '⚡', 'success'),
            $pick('tickets_clear', 'Ticket Master', '📦', 'info'),
            $pick('cctv_clear', 'CCTV Expert', '📹', 'primary') ?? $pick('cctv', 'CCTV Expert', '📹', 'primary'),
            $pick('dismantles_clear', 'Dismantle Hero', '🛠', 'warning'),
        ]));
    }

    /**
     * @param  array<string, int>  $summary
     * @param  array<int, array<string, mixed>>  $nocPerformance
     * @return array<string, mixed>
     */
    protected function buildCharts(
        array $summary,
        array $nocPerformance,
    ): array {
        $rows = $nocPerformance;

        $names = array_map(fn ($row) => (string) $row['name'], $rows);

        $stackedByNoc = [
            'categories' => $names,
            'series' => [
                [
                    'name' => 'Komplain',
                    'data' => array_map(fn ($row) => (int) $row['complaints_clear'], $rows),
                    'color' => '#EF4444',
                ],
                [
                    'name' => 'Aktivasi',
                    'data' => array_map(fn ($row) => (int) $row['activations_clear'], $rows),
                    'color' => '#22C55E',
                ],
                [
                    'name' => 'Ticket',
                    'data' => array_map(fn ($row) => (int) $row['tickets_clear'], $rows),
                    'color' => '#3498DB',
                ],
                [
                    'name' => 'Dismantle',
                    'data' => array_map(fn ($row) => (int) $row['dismantles_clear'], $rows),
                    'color' => '#E67E22',
                ],
                [
                    'name' => 'CCTV',
                    'data' => array_map(fn ($row) => (int) ($row['cctv_clear'] ?? $row['cctv'] ?? 0), $rows),
                    'color' => '#9B59B6',
                ],
            ],
        ];

        // Legacy alias for older clients
        $clearByNoc = [
            'categories' => $names,
            'series' => [[
                'name' => 'Total Clear',
                'data' => array_map(fn ($row) => (int) $row['total'], $rows),
                'color' => '#22C55E',
            ]],
        ];

        $clearByType = [
            'categories' => ['Komplain', 'Aktivasi', 'Ticket', 'Dismantle', 'CCTV'],
            'series' => [[
                'name' => 'Clear',
                'data' => [
                    (int) $summary['complaints_clear'],
                    (int) $summary['activations_clear'],
                    (int) ($summary['tickets_clear'] ?? 0),
                    (int) $summary['dismantles_clear'],
                    (int) ($summary['cctv_clear'] ?? 0),
                ],
                'color' => '#4F46E5',
            ]],
            'colors' => ['#EF4444', '#22C55E', '#3498DB', '#E67E22', '#9B59B6'],
        ];

        $contribution = [
            'categories' => $names,
            'series' => [[
                'name' => 'Kontribusi',
                'data' => array_map(fn ($row) => (float) ($row['contribution_pct'] ?? 0), $rows),
            ]],
            'colors' => ['#3498DB', '#22C55E', '#F59E0B', '#EF4444', '#9B59B6', '#14B8A6', '#E67E22'],
        ];

        return [
            'clear_by_noc' => $clearByNoc,
            'stacked_by_noc' => $stackedByNoc,
            'clear_by_type' => $clearByType,
            'contribution' => $contribution,
        ];
    }

    /**
     * Heatmap produktivitas 7 hari terakhir (Sen–Min) per NOC.
     *
     * @return array{days: list<string>, rows: list<array{user_id: int, name: string, values: list<int>}>}
     */
    protected function weeklyHeatmap(Carbon $to, ?int $userId, ?string $odcName = null): array
    {
        $weekEnd = $to->copy()->endOfDay();
        $weekStart = $to->copy()->subDays(6)->startOfDay();
        $days = [];
        $dayKeys = [];

        $cursor = $weekStart->copy();
        while ($cursor->lte($weekEnd)) {
            $dayKeys[] = $cursor->toDateString();
            $days[] = $cursor->translatedFormat('D');
            $cursor->addDay();
        }

        $totals = [];

        $models = $odcName
            ? [DailyComplaint::class, DailyNocUpdate::class]
            : [DailyActivation::class, DailyComplaint::class, DailyCctvSetup::class, DailyNocUpdate::class];

        foreach ($models as $model) {
            $rows = $model::query()
                ->select(
                    'cleared_by',
                    DB::raw('DATE(COALESCE(cleared_at, report_date)) as clear_date'),
                    DB::raw('COUNT(*) as total'),
                )
                ->where('status', ReportStatus::CLEAR)
                ->whereNotNull('cleared_by')
                ->where(function ($q) use ($weekStart, $weekEnd) {
                    $q->whereBetween('cleared_at', [$weekStart, $weekEnd])
                        ->orWhere(function ($q2) use ($weekStart, $weekEnd) {
                            $q2->whereNull('cleared_at')
                                ->whereBetween('report_date', [$weekStart->toDateString(), $weekEnd->toDateString()]);
                        });
                })
                ->when($userId, fn ($q) => $q->where('cleared_by', $userId))
                ->when($odcName && in_array($model, [DailyComplaint::class, DailyNocUpdate::class], true), fn ($q) => $q->where('odc_name', $odcName))
                ->groupBy('cleared_by', DB::raw('DATE(COALESCE(cleared_at, report_date))'))
                ->get();

            foreach ($rows as $row) {
                if (! $row->clear_date) {
                    continue;
                }
                $uid = (int) $row->cleared_by;
                $date = Carbon::parse($row->clear_date)->toDateString();
                $totals[$uid][$date] = ($totals[$uid][$date] ?? 0) + (int) $row->total;
            }
        }

        $dismantleHeatRows = Dismantle::query()
            ->select(
                DB::raw('COALESCE(assigned_to, created_by) as cleared_by'),
                DB::raw('DATE(COALESCE(closed_at, completed_at, opened_at)) as clear_date'),
                DB::raw('COUNT(*) as total'),
            )
            ->where('status', 'Clear')
            ->where(function ($q) use ($weekStart, $weekEnd) {
                $q->whereBetween('closed_at', [$weekStart->toDateString(), $weekEnd->toDateString()])
                    ->orWhere(function ($q2) use ($weekStart, $weekEnd) {
                        $q2->whereNull('closed_at')
                            ->whereBetween('completed_at', [$weekStart, $weekEnd]);
                    })
                    ->orWhere(function ($q2) use ($weekStart, $weekEnd) {
                        $q2->whereNull('closed_at')
                            ->whereNull('completed_at')
                            ->whereBetween('opened_at', [$weekStart->toDateString(), $weekEnd->toDateString()]);
                    });
            })
            ->when($userId, function ($q) use ($userId) {
                $q->where(function ($q2) use ($userId) {
                    $q2->where('assigned_to', $userId)->orWhere('created_by', $userId);
                });
            })
            ->when($odcName, fn ($q) => $this->scopeDismantleByOdc($q, $odcName))
            ->groupBy(DB::raw('COALESCE(assigned_to, created_by)'), DB::raw('DATE(COALESCE(closed_at, completed_at, opened_at))'))
            ->get();

        foreach ($dismantleHeatRows as $row) {
            if (! $row->clear_date || ! $row->cleared_by) {
                continue;
            }
            $uid = (int) $row->cleared_by;
            $date = Carbon::parse($row->clear_date)->toDateString();
            $totals[$uid][$date] = ($totals[$uid][$date] ?? 0) + (int) $row->total;
        }

        $ticketRows = ReportTicket::query()
            ->select('cleared_by', DB::raw('DATE(COALESCE(cleared_at, closed_at)) as clear_date'), DB::raw('COUNT(*) as total'))
            ->whereNotNull('cleared_by')
            ->whereIn('status', ['Clear', 'Closed'])
            ->where(function ($q) use ($weekStart, $weekEnd) {
                $q->whereBetween('cleared_at', [$weekStart, $weekEnd])
                    ->orWhere(function ($q2) use ($weekStart, $weekEnd) {
                        $q2->whereNull('cleared_at')
                            ->whereBetween('closed_at', [$weekStart->toDateString(), $weekEnd->toDateString()]);
                    });
            })
            ->when($userId, fn ($q) => $q->where('cleared_by', $userId))
            ->when($odcName, fn ($q) => $q->where('odc_name', $odcName))
            ->groupBy('cleared_by', DB::raw('DATE(COALESCE(cleared_at, closed_at))'))
            ->get();

        foreach ($ticketRows as $row) {
            if (! $row->clear_date) {
                continue;
            }
            $uid = (int) $row->cleared_by;
            $date = Carbon::parse($row->clear_date)->toDateString();
            $totals[$uid][$date] = ($totals[$uid][$date] ?? 0) + (int) $row->total;
        }

        $users = User::query()->whereIn('id', array_keys($totals))->get(['id', 'name'])->keyBy('id');

        $heatmapRows = collect($totals)
            ->map(function (array $byDate, $uid) use ($users, $dayKeys) {
                $values = array_map(fn ($d) => (int) ($byDate[$d] ?? 0), $dayKeys);

                return [
                    'user_id' => (int) $uid,
                    'name' => $users->get($uid)?->name ?? 'User #'.$uid,
                    'values' => $values,
                    'total' => array_sum($values),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->map(fn ($row) => [
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'values' => $row['values'],
            ])
            ->all();

        return [
            'days' => $days,
            'rows' => $heatmapRows,
        ];
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
