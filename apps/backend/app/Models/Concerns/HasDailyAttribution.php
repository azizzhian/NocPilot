<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\ReportStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasDailyAttribution
{
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function clearer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function applyClearAttribution(?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        $this->cleared_by = $userId;
        $this->cleared_at = now();
    }

    public function clearClearAttribution(): void
    {
        $this->cleared_by = null;
        $this->cleared_at = null;
    }

    public function syncClearAttribution(string $newStatus, ?int $userId, ?string $previousStatus = null): void
    {
        if ($newStatus === ReportStatus::CLEAR) {
            if ($previousStatus !== ReportStatus::CLEAR || ! $this->cleared_by) {
                $this->applyClearAttribution($userId);
            }

            return;
        }

        if ($previousStatus === ReportStatus::CLEAR || $this->cleared_by) {
            $this->clearClearAttribution();
        }
    }

    /** @return array{created_by: int|null, creator_name: string|null, cleared_by: int|null, clearer_name: string|null, cleared_at: string|null} */
    public function attributionPayload(): array
    {
        return [
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'cleared_by' => $this->cleared_by,
            'clearer_name' => $this->clearer?->name,
            'cleared_at' => $this->cleared_at?->toIso8601String(),
        ];
    }
}
