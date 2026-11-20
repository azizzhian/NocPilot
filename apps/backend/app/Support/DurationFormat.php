<?php

namespace App\Support;

class DurationFormat
{
    /**
     * Format durasi (menit) jadi teks mudah dibaca.
     * 45 → "45 mnt", 60 → "1 jam", 95 → "1 jam 35 mnt", 1500 → "1 hari 1 jam".
     */
    public static function minutes(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        $total = max(0, $minutes);
        if ($total < 60) {
            return $total < 1 ? '<1 mnt' : $total.' mnt';
        }

        $days = intdiv($total, 1440);
        $hours = intdiv($total % 1440, 60);
        $mins = $total % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days.' hari';
        }
        if ($hours > 0) {
            $parts[] = $hours.' jam';
        }
        if ($mins > 0 && $days === 0) {
            $parts[] = $mins.' mnt';
        }

        return implode(' ', $parts);
    }
}
