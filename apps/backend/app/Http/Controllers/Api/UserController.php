<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}
    public function technicians(): JsonResponse
    {
        $users = User::role(['teknisi', 'noc', 'engineer'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'department']);

        return response()->json(['data' => $users]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->with('roles')->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telegram_username', 'like', "%{$search}%");
            });
        }

        return UserResource::collection($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'department' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'telegram_id' => ['nullable', 'string', 'max:64', 'unique:users,telegram_id'],
            'telegram_username' => ['nullable', 'string', 'max:100'],
        ]);

        $username = strtolower(trim($data['username']));
        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            $email = $username.'@nocpilot.local';
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($data['password']),
            'department' => $data['department'] ?? null,
            'status' => $data['status'] ?? 'active',
            'telegram_id' => $data['telegram_id'] ?? null,
            'telegram_username' => isset($data['telegram_username'])
                ? ltrim((string) $data['telegram_username'], '@')
                : null,
        ]);

        $user->assignRole($data['role']);

        $this->activity->log('user', "Tambah user {$user->username}", $request->user(), $request, $user);

        return response()->json([
            'message' => 'User berhasil ditambahkan.',
            'data' => new UserResource($user->load('roles', 'permissions')),
        ], 201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles', 'permissions'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'department' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'in:active,inactive'],
            'role' => ['sometimes', 'string', Rule::exists('roles', 'name')],
            'telegram_id' => ['nullable', 'string', 'max:64', Rule::unique('users', 'telegram_id')->ignore($user->id)],
            'telegram_username' => ['nullable', 'string', 'max:100'],
        ]);

        if (isset($data['username'])) {
            $data['username'] = strtolower(trim($data['username']));
        }
        if (array_key_exists('telegram_username', $data) && $data['telegram_username'] !== null) {
            $data['telegram_username'] = ltrim((string) $data['telegram_username'], '@');
        }
        if (array_key_exists('email', $data) && trim((string) $data['email']) === '') {
            $data['email'] = ($data['username'] ?? $user->username).'@nocpilot.local';
        }

        $user->fill(collect($data)->except(['password', 'role'])->all());

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        $this->activity->log('user', "Edit user {$user->username}", $request->user(), $request, $user);

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'data' => new UserResource($user->load('roles', 'permissions')),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Tidak dapat menghapus akun sendiri.'], 422);
        }

        $username = $user->username;
        $user->delete();

        $this->activity->log('user', "Hapus user {$username}", request()->user(), request());

        return response()->json(['message' => 'User berhasil dihapus.']);
    }
}
