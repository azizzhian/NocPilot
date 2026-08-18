<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $lastReadId = $user->last_read_app_update_id ?? 0;

        $updates = AppUpdate::query()
            ->latest('deployed_at')
            ->limit(20)
            ->get();

        $unread = AppUpdate::query()->where('id', '>', $lastReadId)->count();

        return response()->json([
            'updates' => $updates->map(fn (AppUpdate $u) => [
                'id' => $u->id,
                'from_commit' => $u->from_commit,
                'to_commit' => $u->to_commit,
                'branch' => $u->branch,
                'changes' => $u->changes,
                'deployed_at' => $u->deployed_at?->toIso8601String(),
                'is_unread' => $u->id > $lastReadId,
            ]),
            'unread' => $unread,
            'last_read_id' => $lastReadId ?: null,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $latestId = AppUpdate::query()->max('id');

        if ($latestId) {
            $request->user()->forceFill(['last_read_app_update_id' => $latestId])->save();
        }

        return response()->json([
            'message' => 'Update aplikasi ditandai dibaca.',
            'unread' => 0,
            'last_read_id' => $latestId,
        ]);
    }
}
