<?php

namespace App\Services\Phone;

class PhoneNormalizer
{
    public static function digitsOnly(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $value = number_format((float) $value, 0, '', '');
        }

        return preg_replace('/[^0-9+]/', '', (string) $value) ?? '';
    }

    public static function toLocal(string $phone): ?string
    {
        $digits = self::digitsOnly($phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '+')) {
            $digits = substr($digits, 1);
        }

        if (str_starts_with($digits, '62')) {
            return '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '8')) {
            return '0'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return $digits;
        }

        return $digits;
    }

    /** @return array<int, string> */
    public static function searchVariants(string $phone): array
    {
        $local = self::toLocal($phone);
        $digits = self::digitsOnly($phone);

        $variants = array_filter([
            $local,
            $digits,
            $local ? '62'.ltrim($local, '0') : null,
            $local ? ltrim($local, '0') : null,
        ]);

        return array_values(array_unique($variants));
    }
}
