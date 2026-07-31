<?php

namespace App\Services\Customer;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class RepotCustomerImportService
{
    public int $success = 0;

    public int $failed = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(private CustomerImportService $importer) {}

    public function import(bool $fresh = false): void
    {
        if ($fresh) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            Customer::query()->forceDelete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $query = DB::connection('repot')
            ->table('customers as c')
            ->leftJoin('odcs as o', 'c.odc_id', '=', 'o.id')
            ->select([
                'c.id',
                'c.customer_code',
                'c.name',
                'c.address',
                'c.phone',
                'c.is_active',
                'o.name as odc_name',
            ])
            ->orderBy('c.id');

        foreach ($query->lazyById(500, 'c.id', 'id') as $row) {
            try {
                $this->importRow($row);
                $this->success++;
            } catch (\Throwable $e) {
                $this->failed++;
                $this->errors[] = "Kode {$row->customer_code}: ".$e->getMessage();
            }
        }
    }

    protected function importRow(object $row): void
    {
        $odcName = $row->odc_name;
        if ($odcName === '0' || $odcName === '') {
            $odcName = null;
        }

        $data = [
            'customer_code' => $row->customer_code,
            'name' => $row->name,
            'address' => $row->address,
            'phone' => $row->phone,
            'odc_id' => $odcName ? $this->importer->resolveOdcId($odcName) : null,
            'status' => $row->is_active ? 'active' : 'inactive',
            'imported_at' => now(),
            'pppoe' => $row->customer_code,
        ];

        $existing = Customer::withTrashed()->where('customer_code', $row->customer_code)->first();

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
}
