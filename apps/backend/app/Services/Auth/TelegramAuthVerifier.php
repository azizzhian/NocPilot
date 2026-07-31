<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

class TelegramAuthVerifier
{
    /** @param  array<string, mixed>  $payload */
    public function verify(array $payload): void
    {
        $botToken = (string) Config::get('services.telegram.bot_token');
        if ($botToken === '') {
            throw ValidationException::withMessages([
                'telegram' => ['Login Telegram belum dikonfigurasi di server.'],
            ]);
        }

        $hash = (string) ($payload['hash'] ?? '');
        if ($hash === '') {
            throw ValidationException::withMessages([
                'telegram' => ['Data Telegram tidak valid.'],
            ]);
        }

        $check = $payload;
        unset($check['hash']);
        ksort($check);

        $lines = [];
        foreach ($check as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $lines[] = $key.'='.$value;
        }
        $dataCheckString = implode("\n", $lines);
        $secretKey = hash('sha256', $botToken, true);
        $calculated = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($calculated, $hash)) {
            throw ValidationException::withMessages([
                'telegram' => ['Verifikasi Telegram gagal.'],
            ]);
        }

        $authDate = (int) ($payload['auth_date'] ?? 0);
        if ($authDate < 1 || abs(time() - $authDate) > 86400) {
            throw ValidationException::withMessages([
                'telegram' => ['Sesi Telegram kedaluwarsa. Coba lagi.'],
            ]);
        }
    }
}
