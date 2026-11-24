<?php

namespace App\Services\DailyEntry;

use App\Models\DailyComplaint;
use App\Support\ReportStatus;

class DailyEntryComplaintSerializer
{
    public function __construct(private DailyEntrySerializer $serializer) {}

    /**
     * @param  string|null  $viewDate  Tanggal yang sedang dilihat di Input Harian (untuk flag carry-over)
     * @return array<string, mixed>
     */
    public function serialize(DailyComplaint $complaint, ?string $viewDate = null): array
    {
        $row = $this->serializer->serialize($complaint);
        $actualDate = $complaint->report_date?->toDateString();

        if ($complaint->auto_cleared && empty($row['clearer_name'])) {
            $row['clearer_name'] = 'System / Scheduler';
        }

        $row['is_carryover'] = $viewDate
            && $actualDate
            && $actualDate < $viewDate
            && ReportStatus::isOpen($complaint->status);

        $row['waiting_minutes_left'] = $complaint->waitingMinutesLeft();
        $row['is_repeat'] = (bool) $complaint->parent_complaint_id;
        $row['ticket_id'] = $complaint->ticket_id;
        $row['ticket_status'] = $complaint->ticket?->status;
        $row['escalated_at'] = $complaint->escalated_at?->toIso8601String();
        $row['complaint_number'] = $complaint->complaintNumber();

        if ($complaint->handled_at) {
            $row['handled_at'] = $complaint->handled_at->toIso8601String();
        }

        return $row;
    }
}
