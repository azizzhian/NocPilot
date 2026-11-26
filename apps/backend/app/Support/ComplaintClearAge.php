<?php

namespace App\Support;

use App\Models\DailyComplaint;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ComplaintClearAge
{
    /** Sisa detik sebelum komplain boleh di-Clear; null bila waktu input tidak valid. */
    public static function remainingSeconds(DailyComplaint $complaint, ?Carbon $now = null): ?int
    {
        try {
            $createdAt = $complaint->created_at;
        } catch (\Throwable) {
            $createdAt = null;
        }

        if (! $createdAt instanceof Carbon || $createdAt->getTimestamp() <= 0) {
            return null;
        }

        $ageSeconds = ($now ?? now())->getTimestamp() - $createdAt->getTimestamp();

        return (int) max(0, DailyComplaint::MIN_CLEAR_AGE_SECONDS - $ageSeconds);
    }

    /** Guard umur minimum Clear manual (juga dipakai saat membuat tiket dari komplain). */
    public static function assert(DailyComplaint $complaint, ?Carbon $now = null): void
    {
        $remaining = self::remainingSeconds($complaint, $now);

        if ($remaining === null) {
            throw ValidationException::withMessages([
                'created_at' => 'Waktu input komplain tidak ditemukan atau tidak valid. Komplain belum dapat di-Clear.',
            ]);
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'created_at' => 'Komplain belum dapat di-Clear. Minimal waktu penanganan adalah 5 menit.',
            ]);
        }
    }
}
