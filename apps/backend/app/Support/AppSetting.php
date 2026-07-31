<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AppSetting
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember("app_setting.{$key}", 300, function () use ($key) {
            return DB::table('app_settings')->where('key', $key)->value('value');
        });

        if ($value === null) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $stored = is_bool($value) || is_array($value)
            ? json_encode($value)
            : (string) $value;

        $exists = DB::table('app_settings')->where('key', $key)->exists();

        DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $stored,
                'updated_at' => now(),
                'created_at' => $exists
                    ? DB::table('app_settings')->where('key', $key)->value('created_at')
                    : now(),
            ],
        );

        Cache::forget("app_setting.{$key}");
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
