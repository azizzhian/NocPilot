<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Auth\TelegramAuthVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private ActivityLogger $activity,
        private TelegramAuthVerifier $telegramVerifier,
    ) {}

    public function telegramConfig(): JsonResponse
    {
        $botUsername = (string) Config::get('services.telegram.bot_username', '');
        $enabled = $botUsername !== '' && (string) Config::get('services.telegram.bot_token', '') !== '';

        return response()->json([
            'enabled' => $enabled,
            'bot_username' => $enabled ? $botUsername : null,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $username = strtolower(trim($credentials['username']));
        $user = User::query()->whereRaw('LOWER(username) = ?', [$username])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        return $this->issueToken($request, $user, 'Login berhasil');
    }

    public function loginTelegram(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required'],
            'first_name' => ['nullable', 'string'],
            'last_name' => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'string'],
            'auth_date' => ['required'],
            'hash' => ['required', 'string'],
        ]);

        $this->telegramVerifier->verify($data);

        $telegramId = (string) $data['id'];
        $user = User::query()->where('telegram_id', $telegramId)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'telegram' => ['Akun Telegram belum terhubung. Hubungkan dulu di Kelola User (isi Telegram ID).'],
            ]);
        }

        if (! empty($data['username'])) {
            $user->forceFill(['telegram_username' => ltrim((string) $data['username'], '@')])->save();
        }

        return $this->issueToken($request, $user, 'Login Telegram berhasil');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('roles', 'permissions')),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'department' => ['nullable', 'string', 'max:100'],
            'telegram_id' => ['nullable', 'string', 'max:64', Rule::unique('users', 'telegram_id')->ignore($user->id)],
            'telegram_username' => ['nullable', 'string', 'max:100'],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $wantsPasswordChange = filled($data['password'] ?? null);
        if ($wantsPasswordChange) {
            if (empty($data['current_password']) || ! Hash::check((string) $data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Password saat ini tidak sesuai.'],
                ]);
            }
        }

        $username = strtolower(trim($data['username']));
        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            $email = $username.'@nocpilot.local';
        }

        $user->fill([
            'name' => $data['name'],
            'username' => $username,
            'email' => $email,
            'department' => $data['department'] ?? null,
            'telegram_id' => filled($data['telegram_id'] ?? null) ? (string) $data['telegram_id'] : null,
            'telegram_username' => isset($data['telegram_username']) && $data['telegram_username'] !== ''
                ? ltrim((string) $data['telegram_username'], '@')
                : null,
        ]);

        if ($wantsPasswordChange) {
            $user->password = $data['password'];
        }

        $user->save();

        $this->activity->log('user', "Update profil sendiri ({$user->username})", $user, $request, $user);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => new UserResource($user->fresh()->load('roles', 'permissions')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->activity->log('login', 'Logout', $request->user(), $request);
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    protected function issueToken(Request $request, User $user, string $message): JsonResponse
    {
        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'username' => ['Akun Anda tidak aktif.'],
            ]);
        }

        Auth::login($user);
        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('nocpilot-api')->plainTextToken;
        $this->activity->log('login', $message, $user, $request);

        return response()->json([
            'message' => $message.'.',
            'token' => $token,
            'user' => new UserResource($user->load('roles', 'permissions')),
        ]);
    }
}
