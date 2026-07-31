<?php

namespace App\Services\Customer;

use PhpOffice\PhpSpreadsheet\IOFactory;

class CustomerSpreadsheetReader
{
    /**
     * @return array{header: array<int, string|null>, rows: array<int, array<int, string|null>>}
     */
    public function read(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);

        if ($matrix === []) {
            throw new \InvalidArgumentException('File Excel kosong.');
        }

        $headerIndex = $this->findHeaderRowIndex($matrix);

        if ($headerIndex === null) {
            throw new \InvalidArgumentException('Format tidak didukung. Header wajib memiliki kolom Kode Pelanggan.');
        }

        $header = array_map(
            fn ($cell) => $cell === null ? null : trim((string) $cell),
            $matrix[$headerIndex],
        );

        $rows = [];
        for ($i = $headerIndex + 1, $count = count($matrix); $i < $count; $i++) {
            $row = array_map(
                fn ($cell) => $cell === null ? null : (is_string($cell) ? trim($cell) : (string) $cell),
                $matrix[$i],
            );

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rows[] = $row;
        }

        return ['header' => $header, 'rows' => $rows];
    }

    /** @param  array<int, array<int, mixed>>  $matrix */
    protected function findHeaderRowIndex(array $matrix): ?int
    {
        foreach ($matrix as $index => $row) {
            foreach ($row as $cell) {
                if ($cell === null || $cell === '') {
                    continue;
                }

                $normalized = strtolower(trim((string) $cell));

                if (in_array($normalized, [
                    'kode pelanggan', 'kode_pelanggan', 'id pelanggan', 'id_pelanggan',
                ], true)) {
                    return $index;
                }
            }
        }

        return null;
    }

    /** @param  array<int, string|null>  $row */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
