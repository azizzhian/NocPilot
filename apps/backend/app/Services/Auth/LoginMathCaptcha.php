<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginMathCaptcha
{
    private const CACHE_PREFIX = 'login_math_captcha:';

    private const TTL_SECONDS = 600;

    /** @return array{captcha_id: string, question: string} */
    public function issue(): array
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        $id = (string) Str::uuid();

        Cache::put(self::CACHE_PREFIX.$id, $a * $b, self::TTL_SECONDS);

        return [
            'captcha_id' => $id,
            'question' => "{$a} × {$b}",
        ];
    }

    public function assertValid(string $captchaId, mixed $answer): void
    {
        $key = self::CACHE_PREFIX.$captchaId;
        $expected = Cache::pull($key);

        if ($expected === null) {
            throw ValidationException::withMessages([
                'captcha_answer' => ['Captcha kedaluwarsa. Muat ulang soal.'],
            ]);
        }

        if (! is_numeric($answer) || (int) $answer !== (int) $expected) {
            throw ValidationException::withMessages([
                'captcha_answer' => ['Belajar Lagi'],
            ]);
        }
    }
}
