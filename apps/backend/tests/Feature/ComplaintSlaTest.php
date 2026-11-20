<?php

namespace Tests\Feature;

use App\Models\DailyComplaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ComplaintSlaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sla_kpi_mengikuti_filter_noc_shift_dan_status(): void
    {
        [$nocA, $nocB] = $this->seedComplaints();

        $base = '/api/v1/daily-entry/list/complaints?from='.today()->toDateString().'&to='.today()->toDateString();

        $this->getJson($base)->assertOk()
            ->assertJsonPath('sla.clear', 3)
            ->assertJsonPath('sla.within_60', 2)
            ->assertJsonPath('sla.percent', 66.7);

        $this->getJson($base.'&user_id='.$nocB->id)->assertOk()
            ->assertJsonPath('sla.clear', 0)
            ->assertJsonPath('sla.percent', null);

        $this->getJson($base.'&shift=2')->assertOk()
            ->assertJsonPath('sla.clear', 1)
            ->assertJsonPath('sla.within_60', 1)
            ->assertJsonPath('sla.percent', 100);

        $this->getJson($base.'&status=On-Progress')->assertOk()
            ->assertJsonPath('sla.clear', 0)
            ->assertJsonPath('sla.percent', null)
            ->assertJsonCount(1, 'data');
    }

    public function test_export_rekap_per_noc_dan_shift(): void
    {
        [$nocA, $nocB] = $this->seedComplaints();

        $response = $this->get('/api/v1/daily-entry/export/complaints-recap?from='
            .today()->toDateString().'&to='.today()->toDateString());
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'komplain-rekap').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        unlink($path);

        $this->assertSame([
            'NOC', 'Shift', 'Total komplain', 'Clear', 'On-Progress',
            'Clear ≤60 menit', 'Clear >60 menit', 'Persentase SLA',
        ], $rows[0]);

        $rekap = collect(array_slice($rows, 1));
        $rowA = $rekap->first(fn (array $r) => $r[0] === 'NOC A' && $r[1] === 'Shift 1');
        $rowB = $rekap->first(fn (array $r) => $r[0] === 'NOC B' && $r[1] === 'Shift 1');

        $this->assertEquals(['NOC A', 'Shift 1', 4, 3, 1, 1, 1, '50%'], $rowA);
        $this->assertEquals(['NOC B', 'Shift 1', 1, 0, 1, 0, 0, '—'], $rowB);
    }

    /** @return array{0: User, 1: User} */
    protected function seedComplaints(): array
    {
        $nocA = User::factory()->create(['name' => 'NOC A']);
        $nocB = User::factory()->create(['name' => 'NOC B']);
        Sanctum::actingAs($nocA);

        $today = today()->toDateString();
        $create = function (array $data) use ($today) {
            $model = DailyComplaint::create(['report_date' => $today, 'customer_name' => 'Pelanggan'] + $data);
            // SQLite menyimpan cast 'date' sebagai datetime → paksa tanggal murni agar whereBetween konsisten.
            \Illuminate\Support\Facades\DB::table('daily_complaints')
                ->where('id', $model->id)
                ->update(['report_date' => $today]);

            return $model;
        };

        $create([
            'created_by' => $nocA->id, 'shift' => 1, 'status' => 'Clear',
            'handling_minutes' => 30, 'action' => 'Selesai',
        ]);
        $create([
            'created_by' => $nocA->id, 'shift' => 1, 'status' => 'Clear',
            'handling_minutes' => 90, 'action' => 'Selesai',
        ]);
        $create([
            'created_by' => $nocA->id, 'shift' => 2, 'status' => 'Clear',
            'handling_minutes' => 60, 'action' => 'Selesai',
        ]);
        $create([
            'created_by' => $nocB->id, 'shift' => 1, 'status' => 'On-Progress',
        ]);
        $create([
            'created_by' => $nocA->id, 'shift' => 1, 'status' => 'Menunggu Konfirmasi',
            'action' => 'Selesai ditangani',
        ]);

        // Durasi null → tidak dihitung dalam SLA (baik di halaman Komplain maupun Dashboard).
        $create([
            'created_by' => $nocA->id, 'shift' => 1, 'status' => 'Clear', 'action' => 'Selesai',
        ]);

        return [$nocA, $nocB];
    }
}
