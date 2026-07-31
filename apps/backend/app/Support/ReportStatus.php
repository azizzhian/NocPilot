<?php

namespace App\Support;

class ReportStatus
{
    public const ON_PROGRESS = 'On-Progress';

    public const CLEAR = 'Clear';

    /** @return array<int, string> */
    public static function options(): array
    {
        return [self::ON_PROGRESS, self::CLEAR];
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
