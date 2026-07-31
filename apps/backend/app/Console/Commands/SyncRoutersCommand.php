<?php

namespace App\Console\Commands;

use App\Jobs\SyncAllRoutersJob;
use App\Services\Monitoring\RouterMonitor;
use Illuminate\Console\Command;

class SyncRoutersCommand extends Command
{
    protected $signature = 'routers:sync
        {--router= : Sync specific router by ID or name}
        {--sync : Jalankan sync langsung (tanpa antrian)}';

    protected $description = 'Sinkronisasi metrik router (collector ala Zabbix via queue/scheduler)';

    public function handle(RouterMonitor $monitor): int
    {
        if ($identifier = $this->option('router')) {
            $router = \App\Models\Router::query()
                ->where('id', $identifier)
                ->orWhere('name', $identifier)
                ->firstOrFail();

            $success = $monitor->sync($router);
            $this->info($success ? "OK {$router->name} tersinkronisasi." : "Gagal {$router->name}.");

            return $success ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('sync')) {
            $this->info('Memulai sinkronisasi semua router (langsung)...');
            $results = $monitor->syncAll();
            $this->info("Selesai: {$results['success']} berhasil, {$results['failed']} gagal.");

            return self::SUCCESS;
        }

        SyncAllRoutersJob::dispatch();
        $this->info('Job collector router dikirim ke antrian.');

        return self::SUCCESS;
    }
}