<?php

namespace App\Console\Commands;

use App\Services\Customer\RepotCustomerImportService;
use Illuminate\Console\Command;

class ImportCustomersFromRepotCommand extends Command
{
    protected $signature = 'customers:import-repot {--fresh : Hapus semua pelanggan lalu impor ulang dari repot}';

    protected $description = 'Impor data pelanggan dari database repot (hasil import Excel)';

    public function handle(RepotCustomerImportService $importer): int
    {
        $fresh = (bool) $this->option('fresh');

        if ($fresh && ! $this->confirm('Hapus semua pelanggan NocPilot dan ganti dengan data repot?', false)) {
            $this->warn('Dibatalkan.');

            return self::FAILURE;
        }

        $this->info('Mengimpor pelanggan dari database repot...');

        try {
            $importer->import($fresh);
        } catch (\Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Selesai: {$importer->success} berhasil, {$importer->failed} gagal.");

        if ($importer->errors) {
            $this->warn('Contoh error:');
            foreach (array_slice($importer->errors, 0, 5) as $err) {
                $this->line("  - {$err}");
            }
        }

        return $importer->failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
