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

        $row['is_carryover'] = $viewDate
            && $actualDate
            && $actualDate < $viewDate
            && ReportStatus::isOpen($complaint->status);

        return $row;
    }
}
