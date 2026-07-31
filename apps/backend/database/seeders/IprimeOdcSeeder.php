<?php

namespace Database\Seeders;

use App\Models\Odc;
use App\Models\Pop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IprimeOdcSeeder extends Seeder
{
    /** @var array<int, string> */
    private array $odcNames = [
        'MARGATAMA',
        'JIWAN',
        'MAGETAN',
        'TAKERAN',
        'POLOREJO',
        'SUMOROTO',
        'SOGATEN',
        'KARE',
        'DOLOPO',
        'PONOROGO',
        'TUBAN',
        'JEMBER',
        'PACITAN',
        'BANYUMAS',
        'PURBALINGGA',
        'PWT-BARAT',
        'SOBRAH',
        'KMF-MOJOKERTO',
        'NGANJUK',
        'LAMONGAN',
        'BANJARNEGARA',
        'TINAP',
        'MOJOKERTO',
        'TULUNGAGUNG',
        'HSGQ-TEGUHAN',
    ];

    public function run(): void
    {
        $pop = Pop::query()->find(4) ?? Pop::query()->where('code', '123')->first();

        if (! $pop) {
            $this->command?->error('POP IPRIME (id 4) tidak ditemukan.');

            return;
        }

        foreach ($this->odcNames as $name) {
            Odc::query()->updateOrCreate(
                ['pop_id' => $pop->id, 'name' => $name],
                [
                    'code' => $this->uniqueCode($name),
                    'status' => 'active',
                    'capacity' => 0,
                ],
            );
        }

        $this->command?->info('Berhasil mengisi '.count($this->odcNames)." ODC untuk POP {$pop->name}.");
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '-')) ?: 'ODC';
        $code = $base;
        $i = 1;

        while (Odc::where('code', $code)->where('name', '!=', $name)->exists()) {
            $code = $base.'-'.$i++;
        }

        return $code;
    }
}
