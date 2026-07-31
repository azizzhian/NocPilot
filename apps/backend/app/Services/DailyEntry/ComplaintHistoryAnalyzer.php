<?php

namespace App\Services\DailyEntry;

use App\Models\DailyComplaint;
use App\Support\ReportStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ComplaintHistoryAnalyzer
{
    /**
     * @param  Collection<int, DailyComplaint>  $items
     * @return array{
     *   total: int,
     *   days: int,
     *   count_30d: int,
     *   is_repeat: bool,
     *   open_count: int,
     *   clear_count: int,
     *   clear_rate: int,
     *   avg_clear_hours: float|null,
     *   last_date: string|null,
     *   last_problem: string|null,
     *   score: array{value: int, label: string, level: string, breakdown: list<array{key: string, label: string, points: int}>}
     * }
     */
    public function summarize(Collection $items, int $days, int $total): array
    {
        $now = now();
        $in30 = $items->filter(function (DailyComplaint $c) use ($now) {
            $date = $c->report_date ?? $c->created_at;

            return $date && Carbon::parse($date)->gte($now->copy()->subDays(30)->startOfDay());
        });

        $openCount = $items->filter(fn (DailyComplaint $c) => ! $this->isClear($c->status))->count();
        $clearCount = $items->filter(fn (DailyComplaint $c) => $this->isClear($c->status))->count();
        $clearRate = $total > 0 ? (int) round(($clearCount / max($items->count(), 1)) * 100) : 100;

        $clearDurations = [];
        foreach ($items as $c) {
            if (! $this->isClear($c->status) || ! $c->cleared_at) {
                continue;
            }
            $start = $c->created_at ?? $c->report_date?->startOfDay();
            if (! $start) {
                continue;
            }
            $hours = Carbon::parse($start)->diffInMinutes($c->cleared_at) / 60;
            if ($hours >= 0) {
                $clearDurations[] = $hours;
            }
        }
        $avgClear = $clearDurations !== []
            ? round(array_sum($clearDurations) / count($clearDurations), 1)
            : null;

        $latest = $items->sortByDesc(fn (DailyComplaint $c) => ($c->report_date?->toDateString() ?? '').'-'.$c->id)->first();

        $score = $this->score($items, $in30->count(), $total);

        return [
            'total' => $total,
            'days' => $days,
            'count_30d' => $in30->count(),
            'is_repeat' => $in30->count() >= 2 || $total >= 3,
            'open_count' => $openCount,
            'clear_count' => $clearCount,
            'clear_rate' => $clearRate,
            'avg_clear_hours' => $avgClear,
            'last_date' => $latest?->report_date?->toDateString(),
            'last_problem' => $latest?->problem,
            'score' => $score,
        ];
    }

    /**
     * @param  Collection<int, DailyComplaint>  $items
     * @return array{value: int, label: string, level: string, breakdown: list<array{key: string, label: string, points: int}>}
     */
    public function score(Collection $items, int $count30d, int $total): array
    {
        if ($total === 0) {
            return [
                'value' => 0,
                'label' => 'Sangat Baik',
                'level' => 'good',
                'breakdown' => [],
            ];
        }

        $breakdown = [];
        $value = 0;

        if ($count30d >= 2) {
            $pts = 20;
            $value += $pts;
            $breakdown[] = ['key' => 'repeat_30d', 'label' => 'Komplain berulang dalam 30 hari', 'points' => $pts];
        }

        $problemCounts = [];
        foreach ($items as $c) {
            $key = $this->normalizeProblem($c->problem);
            if ($key === '') {
                continue;
            }
            $problemCounts[$key] = ($problemCounts[$key] ?? 0) + 1;
        }
        $maxSame = $problemCounts !== [] ? max($problemCounts) : 0;
        if ($maxSame >= 2) {
            $pts = 15;
            $value += $pts;
            $breakdown[] = ['key' => 'same_cause', 'label' => 'Penyebab sama berulang', 'points' => $pts];
        }

        $hasLos = $items->contains(function (DailyComplaint $c) {
            return str_contains(strtolower((string) $c->problem), 'los');
        });
        if ($hasLos) {
            $pts = 10;
            $value += $pts;
            $breakdown[] = ['key' => 'los', 'label' => 'Pernah LOS', 'points' => $pts];
        }

        $slaMiss = false;
        foreach ($items as $c) {
            if (! $c->cleared_at) {
                if (! $this->isClear($c->status) && $c->created_at && $c->created_at->lt(now()->subDay())) {
                    $slaMiss = true;
                    break;
                }

                continue;
            }
            $start = $c->created_at ?? $c->report_date?->startOfDay();
            if ($start && Carbon::parse($start)->diffInHours($c->cleared_at) > 24) {
                $slaMiss = true;
                break;
            }
        }
        if ($slaMiss) {
            $pts = 10;
            $value += $pts;
            $breakdown[] = ['key' => 'sla', 'label' => 'Pernah melewati SLA (>24 jam)', 'points' => $pts];
        }

        $latest = $items->sortByDesc(fn (DailyComplaint $c) => ($c->report_date?->toDateString() ?? '').'-'.$c->id)->first();
        $lastDate = $latest?->report_date ?? $latest?->created_at;
        if ($lastDate && Carbon::parse($lastDate)->lt(now()->subDays(60))) {
            $pts = -5;
            $value += $pts;
            $breakdown[] = ['key' => 'quiet', 'label' => 'Lama tidak ada komplain', 'points' => $pts];
        }

        if ($total >= 4) {
            $pts = 15;
            $value += $pts;
            $breakdown[] = ['key' => 'volume', 'label' => 'Volume tinggi (≥4 dalam periode)', 'points' => $pts];
        } elseif ($total >= 2) {
            $pts = 8;
            $value += $pts;
            $breakdown[] = ['key' => 'volume', 'label' => 'Beberapa komplain dalam periode', 'points' => $pts];
        }

        $value = max(0, min(100, $value));

        if ($value <= 24) {
            $level = 'good';
            $label = 'Sangat Baik';
        } elseif ($value <= 49) {
            $level = 'watch';
            $label = 'Perlu Dipantau';
        } else {
            $level = 'risk';
            $label = 'High Risk';
        }

        return [
            'value' => $value,
            'label' => $label,
            'level' => $level,
            'breakdown' => $breakdown,
        ];
    }

    protected function isClear(?string $status): bool
    {
        return strcasecmp((string) $status, ReportStatus::CLEAR) === 0
            || strcasecmp((string) $status, 'clear') === 0;
    }

    protected function normalizeProblem(?string $problem): string
    {
        $p = strtolower(trim((string) $problem));
        $p = preg_replace('/\s+/', ' ', $p) ?? $p;

        return $p;
    }
}
