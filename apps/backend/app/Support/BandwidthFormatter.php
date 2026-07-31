<?php

namespace App\Support;

class BandwidthFormatter
{
    public static function format(int $bps): string
    {
        if ($bps <= 0) {
            return '0 bps';
        }

        if ($bps >= 1_000_000_000) {
            return self::trimDecimal($bps / 1_000_000_000).' Gbps';
        }

        if ($bps >= 1_000_000) {
            return self::trimDecimal($bps / 1_000_000).' Mbps';
        }

        if ($bps >= 1_000) {
            return round($bps / 1_000).' Kbps';
        }

        return $bps.' bps';
    }

    public static function pair(int $rxBps, int $txBps): string
    {
        return self::format($rxBps).' / '.self::format($txBps);
    }

    private static function trimDecimal(float $value): string
    {
        $formatted = number_format($value, 1, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
