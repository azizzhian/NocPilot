<?php

namespace App\Jobs;

use App\Services\Monitoring\RouterMonitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncAllRoutersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 120;

    public function handle(RouterMonitor $monitor): void
    {
        $results = $monitor->syncAll();

        Log::info('Router collector selesai', $results);
    }

    public function uniqueId(): string
    {
        return 'sync-all-routers';
    }
}
