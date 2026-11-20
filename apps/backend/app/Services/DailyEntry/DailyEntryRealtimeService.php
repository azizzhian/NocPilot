<?php

namespace App\Services\DailyEntry;

use App\Models\DailyComplaint;
use App\Models\DailyGamas;
use App\Services\Realtime\RealtimeService;

class DailyEntryRealtimeService
{
    public function __construct(
        private RealtimeService $realtime,
        private DailyEntryComplaintSerializer $serializer,
        private DailyEntrySerializer $entrySerializer,
    ) {}

    public function complaintCreated(DailyComplaint $complaint): void
    {
        $this->broadcast($complaint, 'created');
    }

    public function complaintUpdated(DailyComplaint $complaint): void
    {
        $this->broadcast($complaint, 'updated');
    }

    /** @param  array<string, mixed>|null  $complaintSnapshot */
    public function complaintDeleted(int $complaintId, string $reportDate, ?array $complaintSnapshot = null): void
    {
        $this->realtime->push(
            'complaint.deleted',
            'Komplain dihapus',
            null,
            'info',
            [
                'action' => 'deleted',
                'report_date' => $reportDate,
                'complaint_id' => $complaintId,
                'complaint' => $complaintSnapshot,
            ],
            'daily-entry',
        );
    }

    public function gamasCreated(DailyGamas $gamas): void
    {
        $this->broadcastGamas($gamas, 'created');
    }

    public function gamasUpdated(DailyGamas $gamas): void
    {
        $this->broadcastGamas($gamas, 'updated');
    }

    /** @param  array<string, mixed>|null  $gamasSnapshot */
    public function gamasDeleted(int $gamasId, string $reportDate, ?array $gamasSnapshot = null): void
    {
        $this->realtime->push(
            'gamas.deleted',
            'Gamas dihapus',
            null,
            'info',
            [
                'action' => 'deleted',
                'report_date' => $reportDate,
                'gamas_id' => $gamasId,
                'gamas' => $gamasSnapshot,
            ],
            'gamas',
        );
    }

    protected function broadcastGamas(DailyGamas $gamas, string $action): void
    {
        $gamas->loadMissing(['creator:id,name', 'clearer:id,name']);
        $reportDate = $gamas->report_date?->toDateString() ?? today()->toDateString();
        $serialized = $this->entrySerializer->serialize($gamas, $reportDate);
        $label = $gamas->displayLabel();

        $title = match ($action) {
            'created' => "Gamas baru: {$label}",
            default => "Gamas diperbarui: {$label}",
        };

        $this->realtime->push(
            "gamas.{$action}",
            $title,
            $gamas->problem,
            'info',
            [
                'action' => $action,
                'report_date' => $reportDate,
                'gamas_id' => $gamas->id,
                'gamas' => $serialized,
            ],
            'gamas',
        );
    }

    protected function broadcast(DailyComplaint $complaint, string $action): void
    {
        $complaint->loadMissing(['creator:id,name', 'clearer:id,name']);
        $reportDate = $complaint->report_date?->toDateString() ?? today()->toDateString();
        $serialized = $this->serializer->serialize($complaint, $reportDate);
        $customerName = $complaint->customer_name ?: 'Pelanggan';

        $title = match ($action) {
            'created' => "Komplain baru: {$customerName}",
            default => "Komplain diperbarui: {$customerName}",
        };

        $this->realtime->push(
            "complaint.{$action}",
            $title,
            $complaint->problem,
            'info',
            [
                'action' => $action,
                'report_date' => $reportDate,
                'complaint_id' => $complaint->id,
                'complaint' => $serialized,
            ],
            'daily-entry',
        );
    }
}
