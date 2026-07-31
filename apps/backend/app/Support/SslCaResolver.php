<?php

namespace App\Support;

class SslCaResolver
{
    /** @return array{verify: string|bool} */
    public static function httpClientOptions(): array
    {
        $bundle = self::resolve();

        return ['verify' => $bundle ?? true];
    }

    public static function resolve(): ?string
    {
        $configured = trim((string) config('services.http.ca_bundle', ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $envPath = trim((string) env('SSL_CA_BUNDLE', ''));
        if ($envPath !== '' && is_file($envPath)) {
            return $envPath;
        }

        foreach (self::candidatePaths() as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $iniPath = trim((string) ini_get('curl.cainfo'));
        if ($iniPath !== '' && is_file($iniPath)) {
            return $iniPath;
        }

        return null;
    }

    /** @return array<int, string> */
    protected static function candidatePaths(): array
    {
        return array_values(array_unique(array_filter([
            base_path('resources/certs/cacert.pem'),
            'C:\\laragon\\etc\\ssl\\cacert.pem',
            'C:/laragon/etc/ssl/cacert.pem',
            dirname(base_path(), 4).'/etc/ssl/cacert.pem',
        ])));
    }
}
