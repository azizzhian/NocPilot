<?php

namespace App\Support;

class TicketStatus
{
    public const OPEN = 'Open';
    public const ASSIGNED = 'Assigned';
    public const ON_SITE = 'On-Site';
    public const IN_PROGRESS = 'In-Progress';
    public const COMPLETED = 'Completed';
    public const CANCELLED = 'Cancelled';

    public const LEGACY_ON_PROGRESS = 'On-Progress';
    public const LEGACY_CLEAR = 'Clear';
    public const LEGACY_CLOSED = 'Closed';

    public const CLOSE = self::LEGACY_CLOSED;

    /** @return list<string> */
    public static function options(): array
    {
        return [
            self::OPEN,
            self::ASSIGNED,
            self::ON_SITE,
            self::IN_PROGRESS,
            self::COMPLETED,
            self::CANCELLED,
            self::LEGACY_ON_PROGRESS,
            self::LEGACY_CLEAR,
            self::LEGACY_CLOSED,
        ];
    }

    /** @return list<string> */
    public static function formOptions(): array
    {
        return [self::LEGACY_ON_PROGRESS, self::CLOSE];
    }

    /** @return list<string> */
    public static function legacyStatuses(): array
    {
        return [
            self::OPEN,
            self::ASSIGNED,
            self::ON_SITE,
            self::IN_PROGRESS,
            self::COMPLETED,
            self::CANCELLED,
        ];
    }

    /** Status yang masih dikerjakan — dipakai untuk cegah tiket aktif ganda & proteksi auto-clear. */
    public static function activeStatuses(): array
    {
        return [
            self::OPEN,
            self::ASSIGNED,
            self::ON_SITE,
            self::IN_PROGRESS,
            self::LEGACY_ON_PROGRESS,
        ];
    }

    /** Status aktif yang dihitung sebagai KPI Tiket Aktif. */
    public static function kpiActiveStatuses(): array
    {
        return [self::LEGACY_ON_PROGRESS];
    }

    /** Status selesai sesuai ketentuan baru: Clear historis + Closed (label Close). */
    public static function closeStatuses(): array
    {
        return [self::LEGACY_CLEAR, self::LEGACY_CLOSED];
    }

    /** Status yang memicu sinkronisasi clear/complaint pada workflow lama. */
    public static function finishedStatuses(): array
    {
        return [
            self::COMPLETED,
            self::LEGACY_CLEAR,
            self::LEGACY_CLOSED,
        ];
    }

    public static function isActive(?string $status): bool
    {
        return in_array($status, self::activeStatuses(), true);
    }

    public static function isKpiActive(?string $status): bool
    {
        return in_array($status, self::kpiActiveStatuses(), true);
    }

    public static function isClose(?string $status): bool
    {
        return in_array($status, self::closeStatuses(), true);
    }

    public static function isFinished(?string $status): bool
    {
        return in_array($status, self::finishedStatuses(), true);
    }

    public static function isCancelled(?string $status): bool
    {
        return $status === self::CANCELLED;
    }

    public static function isClearMode(?string $status): bool
    {
        return self::isFinished($status);
    }

    public static function displayLabel(?string $status): string
    {
        return match ($status) {
            self::LEGACY_CLOSED => 'Close',
            default => (string) $status,
        };
    }

    /** @return list<string>|null */
    public static function filterStatuses(?string $status): ?array
    {
        if ($status === null || $status === '' || $status === 'all') {
            return null;
        }
        if (strcasecmp($status, 'close') === 0) {
            return self::closeStatuses();
        }

        return in_array($status, self::options(), true) ? [$status] : null;
    }
}
