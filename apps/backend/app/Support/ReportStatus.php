<?php

namespace App\Support;

class ReportStatus
{
    public const ON_PROGRESS = 'On-Progress';

    /** Khusus komplain: NOC sudah selesai menangani, menunggu konfirmasi pelanggan (timer auto-clear 30 menit). */
    public const WAITING_CONFIRMATION = 'Menunggu Konfirmasi';

    public const CLEAR = 'Clear';

    /** @return array<int, string> */
    public static function options(): array
    {
        return [self::ON_PROGRESS, self::CLEAR];
    }

    /** Opsi status khusus komplain (input harian). @return array<int, string> */
    public static function complaintOptions(): array
    {
        return [self::ON_PROGRESS, self::WAITING_CONFIRMATION, self::CLEAR];
    }

    public static function isClear(?string $status): bool
    {
        return strcasecmp((string) $status, self::CLEAR) === 0;
    }

    public static function isOpen(?string $status): bool
    {
        return ! self::isClear($status);
    }
}
