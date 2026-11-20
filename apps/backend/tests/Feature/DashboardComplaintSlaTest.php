<?php

namespace Tests\Feature;

use App\Models\DailyComplaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardComplaintSlaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sla_dashboard_memakai_aturan_yang_sama_dengan_halaman_komplain(): void
    {
        [$nocA, $nocB] = $this->seedComplaints();
        $base = '/api/v1/dashboard/stats?period=custom&from='.today()->toDateString().'&to='.today()->toDateString();

        $this->getJson($base)->assertOk()
            ->assertJsonPath('complaint_performance.summary.sla.clear', 4)
            ->assertJsonPath('complaint_performance.summary.sla.within_60', 3)
            ->assertJsonPath('complaint_performance.summary.sla.percent', 75)
            ->assertJsonPath('complaint_performance.rows.0.sla.clear', 3)
            ->assertJsonPath('complaint_performance.rows.0.sla.percent', 66.7)
            ->assertJsonPath('complaint_performance.rows.1.sla.percent', 100);

        $this->getJson($base.'&user_id='.$nocA->id)->assertOk()
            ->assertJsonPath('complaint_performance.summary.sla.clear', 3)
            ->assertJsonPath('complaint_performance.summary.sla.within_60', 2)
            ->assertJsonPath('complaint_performance.summary.sla.percent', 66.7);

        $this->getJson($base.'&shift=2')->assertOk()
            ->assertJsonPath('complaint_performance.summary.sla.clear', 1)
            ->assertJsonPath('complaint_performance.summary.sla.percent', 100);

        $this->getJson($base.'&user_id='.$nocA->id.'&shift=1')->assertOk()
            ->assertJsonPath('complaint_performance.summary.sla.clear', 2)
            ->assertJsonPath('complaint_performance.summary.sla.within_60', 1)
            ->assertJsonPath('complaint_performance.summary.sla.percent', 50);
    }

    public function test_sla_dashboard_null_bila_tidak_ada_durasi_valid(): void
    {
        $this->seedComplaints();
        $tomorrow = today()->addDay()->toDateString();

        $this->getJson('/api/v1/dashboard/stats?period=custom&from='.$tomorrow.'&to='.$tomorrow)
            ->assertOk()
            ->assertJsonPath('complaint_performance.summary.sla.clear', 0)
            ->assertJsonPath('complaint_performance.summary.sla.percent', null);
    }

    /** @return array{0: User, 1: User} */
    protected function seedComplaints(): array
    {
        $nocA = User::factory()->create(['name' => 'NOC A']);
        $nocB = User::factory()->create(['name' => 'NOC B']);
        Sanctum::actingAs($nocA);

        $today = today()->toDateString();
        $create = function (array $data) use ($today) {
            $model = DailyComplaint::create(array_merge([
                'report_date' => $today,
                'customer_name' => 'Pelanggan',
                'cleared_at' => now(),
                'status' => 'Clear',
            ], $data));
            \Illuminate\Support\Facades\DB::table('daily_complaints')
                ->where('id', $model->id)
                ->update(['report_date' => $today]);

            return $model;
        };

        foreach ([30, 90] as $minutes) {
            $create([
                'created_by' => $nocA->id, 'cleared_by' => $nocA->id,
                'shift' => 1, 'handling_minutes' => $minutes, 'action' => 'Selesai',
            ]);
        }
        $create([
            'created_by' => $nocA->id, 'cleared_by' => $nocA->id,
            'shift' => 2, 'handling_minutes' => 60, 'action' => 'Selesai',
        ]);
        $create([
            'created_by' => $nocA->id, 'cleared_by' => $nocA->id,
            'shift' => 1, 'handling_minutes' => null, 'action' => 'Selesai',
        ]);
        $create([
            'created_by' => $nocB->id, 'cleared_by' => $nocB->id,
            'shift' => 1, 'handling_minutes' => 20, 'action' => 'Selesai',
        ]);
        $create([
            'created_by' => $nocA->id, 'shift' => 1,
            'status' => 'On-Progress', 'cleared_at' => null,
        ]);

        return [$nocA, $nocB];
    }
}
