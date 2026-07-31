<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    public function log(
        string $type,
        string $action,
        ?User $user = null,
        ?Request $request = null,
        ?Model $subject = null,
        ?array $meta = null,
    ): ActivityLog {
        $agent = $request?->userAgent() ?? '';

        return ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'type' => $type,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $agent ? substr($agent, 0, 500) : null,
            'device' => $this->parseDevice($agent),
            'browser' => $this->parseBrowser($agent),
            'meta' => $meta,
        ]);
    }

    protected function parseDevice(string $ua): string
    {
        if (str_contains($ua, 'Windows')) return 'Windows';
        if (str_contains($ua, 'Macintosh')) return 'macOS';
        if (str_contains($ua, 'Android')) return 'Android';
        if (str_contains($ua, 'iPhone')) return 'iOS';
        if ($ua === '') return 'Server';

        return 'Unknown';
    }

    protected function parseBrowser(string $ua): string
    {
        if (str_contains($ua, 'Edg/')) return 'Edge';
        if (str_contains($ua, 'Chrome/')) return 'Chrome';
        if (str_contains($ua, 'Firefox/')) return 'Firefox';
        if (str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome')) return 'Safari';
        if ($ua === '') return '—';

        return 'Unknown';
    }
}
