<?php

namespace App\Services\Gamas;

use App\Models\DailyGamas;
use App\Support\ReportStatus;

class GamasStatsService
{
    /**
     * Statistik gamas untuk rentang tanggal (dipakai halaman Gamas & widget dashboard).
     *
     * @return array{
     *     summary: array{total: int, open_all: int, clear: int},
     *     by_kind: list<array{kind: string, label: string, total: int, open: int, clear: int}>,
     *     by_odc: list<array{odc_name: string, total: int, open: int, clear: int}>
     * }
     */
    public function forRange(string $from, string $to): array
    {
        $clearLower = strtolower(ReportStatus::CLEAR);

        $total = DailyGamas::query()->whereBetween('report_date', [$from, $to])->count();

        // Open kumulatif (input <= to, belum Clear) — menampilkan backlog seperti KPI Input Harian.
        $openAll = DailyGamas::query()
            ->whereDate('report_date', '<=', $to)
            ->where(function ($q) use ($clearLower) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(status) <> ?', [$clearLower]);
            })
            ->count();

        $clear = $this->clearedInRangeQuery($from, $to)->count();

        $kindRows = DailyGamas::query()
            ->whereBetween('report_date', [$from, $to])
            ->selectRaw('gamas_kind, COUNT(*) as total, SUM(CASE WHEN LOWER(status) <> ? THEN 1 ELSE 0 END) as open_count', [$clearLower])
            ->groupBy('gamas_kind')
            ->get();

        $kindClears = $this->clearedInRangeQuery($from, $to)
            ->selectRaw('gamas_kind, COUNT(*) as total')
            ->groupBy('gamas_kind')
            ->get()
            ->keyBy('gamas_kind');

        $byKind = collect(DailyGamas::KINDS)->map(function (string $kind) use ($kindRows, $kindClears) {
            $row = $kindRows->firstWhere('gamas_kind', $kind);

            return [
                'kind' => $kind,
                'label' => DailyGamas::kindLabel($kind),
                'total' => (int) ($row->total ?? 0),
                'open' => (int) ($row->open_count ?? 0),
                'clear' => (int) ($kindClears->get($kind)?->total ?? 0),
            ];
        })->values()->all();

        $odcRows = DailyGamas::query()
            ->whereBetween('report_date', [$from, $to])
            ->selectRaw('odc_name, COUNT(*) as total, SUM(CASE WHEN LOWER(status) <> ? THEN 1 ELSE 0 END) as open_count', [$clearLower])
            ->groupBy('odc_name')
            ->get();

        $odcClears = $this->clearedInRangeQuery($from, $to)
            ->selectRaw('odc_name, COUNT(*) as total')
            ->groupBy('odc_name')
            ->get()
            ->keyBy(fn ($row) => mb_strtolower(trim((string) $row->odc_name)));

        $byOdc = $odcRows->map(function ($row) use ($odcClears) {
            $key = mb_strtolower(trim((string) $row->odc_name));

            return [
                'odc_name' => trim((string) $row->odc_name) !== '' ? trim((string) $row->odc_name) : 'Tanpa ODC',
                'total' => (int) $row->total,
                'open' => (int) $row->open_count,
                'clear' => (int) ($odcClears->get($key)?->total ?? 0),
            ];
        })->sortByDesc('total')->values()->take(10)->all();

        return [
            'summary' => [
                'total' => $total,
                'open_all' => $openAll,
                'clear' => $clear,
            ],
            'by_kind' => $byKind,
            'by_odc' => $byOdc,
        ];
    }

    /**
     * Status Clear + cleared_at dalam rentang (fallback report_date bila cleared_at null).
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\DailyGamas>
     */
    protected function clearedInRangeQuery(string $from, string $to)
    {
        $fromStart = \Carbon\Carbon::parse($from)->startOfDay();
        $toEnd = \Carbon\Carbon::parse($to)->endOfDay();

        return DailyGamas::query()
            ->whereRaw('LOWER(status) = ?', [strtolower(ReportStatus::CLEAR)])
            ->where(function ($q) use ($from, $to, $fromStart, $toEnd) {
                $q->whereBetween('cleared_at', [$fromStart, $toEnd])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('cleared_at')
                            ->whereBetween('report_date', [$from, $to]);
                    });
            });
    }
}
