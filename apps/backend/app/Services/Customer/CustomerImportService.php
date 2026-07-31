<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\Odc;
use App\Models\Pop;
use Illuminate\Support\Str;

class CustomerImportService
{
    public int $success = 0;

    public int $failed = 0;

    /** @var array<int, string> */
    public array $errors = [];

    /** @param  array<int, string|null>  $header */
    /** @param  array<int, array<int, string|null>>  $rows */
    public function import(array $header, array $rows): void
    {
        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $header);

        if (! in_array('kode_pelanggan', $header, true)) {
            throw new \InvalidArgumentException('Format tidak didukung. Header wajib memiliki kolom Kode Pelanggan.');
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            try {
                $this->importRow($header, $row);
                $this->success++;
            } catch (\Throwable $e) {
                $this->failed++;
                $this->errors[] = "Baris {$rowNumber}: ".$e->getMessage();
            }
        }
    }

    /** @param  array<int, string|null>  $header */
    /** @param  array<int, string|null>  $row */
    protected function importRow(array $header, array $row): void
    {
        $assoc = [];
        foreach ($header as $i => $key) {
            if ($key !== '') {
                $assoc[$key] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }
        }

        $name = $this->value($assoc, ['nama_pelanggan', 'nama_pelanggan', 'nama']);
        $customerCode = $this->value($assoc, ['kode_pelanggan', 'kode_pelanggan', 'id_pelanggan', 'id_pelanggan']);
        $address = $this->value($assoc, ['alamat', 'address']);
        $phone = $this->phoneValue($assoc);
        $odcName = $this->value($assoc, ['odc', 'nama_odc', 'nama_odc']);

        if ($odcName === '0') {
            $odcName = null;
        }

        if (! $name) {
            throw new \InvalidArgumentException('Nama pelanggan wajib diisi.');
        }

        if (! $customerCode) {
            throw new \InvalidArgumentException('Kode pelanggan wajib diisi.');
        }

        $data = [
            'customer_code' => $customerCode,
            'name' => $name,
            'address' => $address,
            'odc_id' => $odcName ? $this->resolveOdc($odcName) : null,
            'status' => 'active',
            'imported_at' => now(),
            'pppoe' => $customerCode,
        ];

        if ($phone !== null) {
            $data['phone'] = $phone;
        }

        $existing = Customer::withTrashed()->where('customer_code', $customerCode)->first();

        if ($existing?->trashed()) {
            $existing->restore();
        }

        if ($existing) {
            $existing->update($data);

            return;
        }

        Customer::create([
            ...$data,
            'activated_at' => now(),
        ]);
    }

    public function resolveOdcId(string $name): int
    {
        return $this->resolveOdc($name);
    }

    protected function resolveOdc(string $name): int
    {
        $odc = Odc::query()->where('name', $name)->first();

        if ($odc) {
            return $odc->id;
        }

        return Odc::create([
            'pop_id' => $this->defaultPopId(),
            'name' => $name,
            'code' => $this->uniqueOdcCode($name),
            'status' => 'active',
        ])->id;
    }

    protected function defaultPopId(): int
    {
        $popId = Pop::query()->value('id');

        if ($popId) {
            return (int) $popId;
        }

        return Pop::create([
            'name' => 'POP Default',
            'code' => 'POP-DEFAULT',
            'status' => 'active',
            'capacity' => 0,
        ])->id;
    }

    protected function uniqueOdcCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '-')) ?: 'ODC';
        $code = $base;
        $i = 1;

        while (Odc::where('code', $code)->exists()) {
            $code = $base.'-'.$i++;
        }

        return $code;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return Str::slug(strtolower(trim($header)), '_');
    }

    /** @param  array<string, string>  $row */
    protected function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $slug = Str::slug($key, '_');
            if (! empty($row[$slug])) {
                return $row[$slug];
            }
        }

        return null;
    }

    /** @param  array<string, string>  $row */
    protected function phoneValue(array $row): ?string
    {
        $aliases = [
            'no_hp', 'no_hp', 'nohp', 'nomor_hp', 'nomor_handphone',
            'no_telp', 'no_telepon', 'telepon', 'phone', 'hp', 'whatsapp', 'wa',
        ];

        $value = $this->value($row, $aliases);
        if ($value !== null) {
            return $this->normalizePhone($value);
        }

        foreach ($row as $key => $cell) {
            if ($cell === null || $cell === '') {
                continue;
            }

            $normalized = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $key));

            if (
                str_contains($normalized, 'handphone')
                || str_contains($normalized, 'whatsapp')
                || (str_contains($normalized, 'nomor') && (str_contains($normalized, 'hp') || str_contains($normalized, 'tel')))
                || (str_contains($normalized, 'no') && (str_contains($normalized, 'hp') || str_contains($normalized, 'tel')))
            ) {
                return $this->normalizePhone($cell);
            }
        }

        return null;
    }

    protected function normalizePhone(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $value = number_format((float) $value, 0, '', '');
        }

        $phone = preg_replace('/[^0-9+]/', '', (string) $value);

        if ($phone === '') {
            return null;
        }

        if (! str_starts_with($phone, '0') && ! str_starts_with($phone, '+') && str_starts_with($phone, '8') && strlen($phone) >= 10) {
            $phone = '0'.$phone;
        }

        return $phone;
    }

    /** @param  array<int, string|null>  $row */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
