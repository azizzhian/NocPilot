<?php

namespace App\Services\Dismantle;

use App\Models\Customer;
use App\Models\Dismantle;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DismantleImportService
{
    public int $success = 0;

    public int $skipped = 0;

    public int $failed = 0;

    /** @var array<int, string> */
    public array $errors = [];

    /** @var array<int, string> */
    public array $skippedMessages = [];

    /** @var array<string, true> */
    private array $seenCodes = [];

    public function __construct(private int $userId) {}

    /** @param  array<int, string|null>  $header */
    /** @param  array<int, array<int, string|null>>  $rows */
    public function import(array $header, array $rows): void
    {
        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $header);

        $hasId = collect($header)->contains(fn ($h) => in_array($h, [
            'id_pel', 'id_pelanggan', 'customer_code', 'kode_pelanggan', 'kode',
        ], true));

        if (! $hasId) {
            throw new \InvalidArgumentException('Format tidak didukung. Header wajib memiliki kolom ID Pel (id_pel / customer_code / kode_pelanggan).');
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            try {
                $this->importRow($header, $row, $rowNumber);
            } catch (\Throwable $e) {
                $this->failed++;
                $this->errors[] = "Baris {$rowNumber}: ".$e->getMessage();
            }
        }
    }

    /** @param  array<int, string|null>  $header */
    /** @param  array<int, string|null>  $row */
    private function importRow(array $header, array $row, int $rowNumber): void
    {
        $assoc = [];
        foreach ($header as $i => $key) {
            if ($key !== '') {
                $assoc[$key] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }
        }

        $code = $this->value($assoc, ['id_pel', 'id_pelanggan', 'customer_code', 'kode_pelanggan', 'kode']);
        $name = $this->value($assoc, ['nama', 'nama_pelanggan', 'customer_name']);
        $location = $this->value($assoc, ['lokasi', 'location', 'area', 'site']);
        $status = $this->normalizeStatus($this->value($assoc, ['status', 'status_tiket']) ?? 'On-Progress');
        $openedAt = $this->parseDate($this->value($assoc, ['open_ticket', 'opened_at', 'open', 'tanggal_open']));
        $closedAt = $this->parseDate($this->value($assoc, ['close_ticket', 'closed_at', 'close', 'tanggal_close']));

        if (! $code) {
            throw new \InvalidArgumentException('ID Pel wajib diisi.');
        }

        $normalized = strtolower($code);
        $customer = Customer::query()
            ->with('odc:id,name')
            ->whereRaw('LOWER(TRIM(customer_code)) = ?', [$normalized])
            ->first();

        if (! $name) {
            $name = $customer?->name;
        }
        if (! $location) {
            $location = $customer?->area ?? $customer?->odc?->name;
        }
        if (! $name) {
            throw new \InvalidArgumentException("Nama wajib diisi (ID Pel {$code} tidak ditemukan di master pelanggan).");
        }

        if ($status !== 'Clear') {
            if (isset($this->seenCodes[$normalized])) {
                $this->skipped++;
                $this->skippedMessages[] = "Baris {$rowNumber}: ID Pel {$code} dilewati, duplikat di file yang sama.";

                return;
            }

            $existing = Dismantle::findOpenByCustomerCode($code);
            if ($existing) {
                $this->skipped++;
                $this->skippedMessages[] = "Baris {$rowNumber}: ID Pel {$code} dilewati, sudah ada tiket terbuka ({$existing->reference}).";

                return;
            }

            $this->seenCodes[$normalized] = true;
        }

        Dismantle::create([
            'reference' => Dismantle::generateReference(),
            'customer_id' => $customer?->id,
            'customer_name' => $name,
            'customer_code' => $code,
            'location' => $location,
            'status' => $status,
            'opened_at' => $openedAt ?? now()->toDateString(),
            'closed_at' => $status === 'Clear' ? ($closedAt ?? now()->toDateString()) : $closedAt,
            'created_by' => $this->userId,
        ]);

        $this->success++;
    }

    private function normalizeStatus(string $status): string
    {
        $key = strtolower(trim($status));
        $key = str_replace(['_', ' '], '-', $key);

        return match ($key) {
            'pending' => 'Pending',
            'clear', 'selesai', 'closed', 'done' => 'Clear',
            default => 'On-Progress',
        };
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return Str::slug(strtolower(trim($header)), '_');
    }

    /** @param  array<string, string>  $row */
    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $slug = Str::slug($key, '_');
            if (! empty($row[$slug])) {
                return $row[$slug];
            }
        }

        return null;
    }

    /** @param  array<int, string|null>  $row */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
