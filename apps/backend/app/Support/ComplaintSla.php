<?php

namespace App\Support;

use App\Models\DailyComplaint;

class ComplaintSla
{
    public const LIMIT_MINUTES = 60;

    public static function minutes(DailyComplaint $complaint): ?int
    {
        return $complaint->handling_minutes === null
            ? null
            : (int) $complaint->handling_minutes;
    }

    /**
     * Ringkasan SLA hanya dari komplain Clear dengan handling_minutes valid.
     * Data dengan durasi null tidak masuk pembilang maupun penyebut.
     *
     * @param  iterable<DailyComplaint>  $complaints
     * @return array{clear: int, within_60: int, percent: float|null}
     */
    public static function summary(iterable $complaints): array
    {
        $clear = 0;
        $within = 0;

        foreach ($complaints as $complaint) {
            if (! ReportStatus::isClear($complaint->status)) {
                continue;
            }

            $minutes = self::minutes($complaint);
            if ($minutes === null) {
                continue;
            }

            $clear++;
            if ($minutes <= self::LIMIT_MINUTES) {
                $within++;
            }
        }

        return [
            'clear' => $clear,
            'within_60' => $within,
            'percent' => $clear > 0 ? round($within / $clear * 100, 1) : null,
        ];
    }
}
