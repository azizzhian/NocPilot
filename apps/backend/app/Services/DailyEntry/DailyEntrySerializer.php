<?php

namespace App\Services\DailyEntry;

use App\Support\ReportStatus;
use Illuminate\Database\Eloquent\Model;

class DailyEntrySerializer
{
    /** @return array<string, mixed> */
    public function serialize(Model $item, ?string $viewDate = null): array
    {
        $row = $item->toArray();

        if (isset($row['report_date']) && $item->report_date) {
            $row['report_date'] = $item->report_date->toDateString();
        }

        foreach (['start_problem', 'end_problem', 'start_ticket', 'close_ticket'] as $dateField) {
            if (isset($item->{$dateField}) && $item->{$dateField}) {
                $row[$dateField] = $item->{$dateField}->toDateString();
            }
        }

        if (method_exists($item, 'attributionPayload')) {
            $row = [...$row, ...$item->attributionPayload()];
        }

        if ($item->created_at) {
            $row['created_at'] = $item->created_at->toIso8601String();
        }

        $actualDate = $row['report_date'] ?? null;
        $status = $row['status'] ?? ($item->status ?? null);
        $row['is_carryover'] = $viewDate
            && $actualDate
            && $actualDate < $viewDate
            && ReportStatus::isOpen(is_string($status) ? $status : null);

        return $row;
    }
}
