<?php

namespace App\Console\Commands;

use App\Models\DailyComplaint;
use App\Services\Audit\ActivityLogger;
use App\Services\DailyEntry\DailyEntryRealtimeService;
use App\Support\ReportStatus;
use Illuminate\Console\Command;

class AutoClearWaitingComplaintsCommand extends Command
{
    protected $signature = 'complaint:auto-clear';

    protected $description = 'Auto-clear komplain "Menunggu Konfirmasi" setelah 30 menit tanpa konfirmasi pelanggan';

    public function handle(
        DailyEntryRealtimeService $realtime,
        ActivityLogger $activity,
    ): int {
        $due = DailyComplaint::query()
            ->where('status', ReportStatus::WAITING_CONFIRMATION)
            ->whereNotNull('handled_at')
            ->where('handled_at', '<=', now()->subMinutes(DailyComplaint::WAITING_CONFIRMATION_MINUTES))
            ->orderBy('id')
            ->get();

        if ($due->isEmpty()) {
            $this->line('Tidak ada komplain menunggu konfirmasi yang jatuh tempo.');

            return self::SUCCESS;
        }

        foreach ($due as $complaint) {
            $previous = $complaint->status;

            // Auto-clear adalah aksi sistem: jangan memakai identitas petugas
            // (created_by/handled_by) sebagai pelaku perubahan status.
            $complaint->status = ReportStatus::CLEAR;
            $complaint->cleared_by = null;
            $complaint->cleared_at = now();
            $complaint->syncClearAttribution(ReportStatus::CLEAR, null, $previous);
            $complaint->syncHandling(ReportStatus::CLEAR, null, $previous);
            $complaint->auto_cleared = true;
            if (! $complaint->end_problem) {
                $complaint->end_problem = today();
            }
            $complaint->save();

            $fresh = $complaint->fresh(['creator:id,name', 'clearer:id,name', 'handler:id,name']);
            $realtime->complaintUpdated($fresh);
            $activity->log(
                'complaint',
                'Auto-clear komplain '.$fresh->displayLabel().' oleh System / Scheduler (menunggu konfirmasi '.DailyComplaint::WAITING_CONFIRMATION_MINUTES.' menit)',
                null,
                null,
                $fresh,
                ['source' => 'scheduler', 'actor' => 'System / Scheduler'],
                'System / Scheduler',
            );

            $this->info("Auto-clear #{$fresh->id} {$fresh->displayLabel()}");
        }

        $this->info("Selesai: {$due->count()} komplain di-auto-clear.");

        return self::SUCCESS;
    }
}
