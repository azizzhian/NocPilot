<?php

namespace Tests\Feature;

use App\Models\DailyComplaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_auto_clear_digabung_ke_baris_pembuat_bukan_system_scheduler(): void
    {
        $noc = User::factory()->create(['name' => 'NOC Auto']);
        $other = User::factory()->create(['name' => 'NOC Lain']);
        Sanctum::actingAs($noc);
        $today = today()->toDateString();

        $complaint = DailyComplaint::create([
            'report_date' => $today,
            'customer_name' => 'Pelanggan',
            'status' => 'Clear',
            'created_by' => $noc->id,
            'cleared_by' => null,
            'cleared_at' => now(),
            'auto_cleared' => true,
            'handling_minutes' => 90,
            'performance_category' => 'Cukup Lambat',
            'action' => 'Auto clear',
        ]);
        DB::table('daily_complaints')->where('id', $complaint->id)->update(['report_date' => $today]);

        $base = '/api/v1/dashboard/stats?period=custom&from='.$today.'&to='.$today;

        $this->getJson($base)->assertOk()
            ->assertJsonMissing(['name' => 'System / Scheduler'])
            ->assertJsonPath('complaint_performance.summary.auto_cleared', 1)
            ->assertJsonCount(1, 'complaint_performance.rows')
            ->assertJsonPath('complaint_performance.rows.0.name', 'NOC Auto')
            ->assertJsonPath('complaint_performance.rows.0.clear', 1)
            ->assertJsonPath('complaint_performance.rows.0.auto_cleared', 1)
            ->assertJsonPath('complaint_performance.rows.0.categories.Cukup Lambat', 1)
            ->assertJsonPath('complaint_performance.rows.0.sla.clear', 1);

        $this->getJson($base.'&user_id='.$noc->id)->assertOk()
            ->assertJsonPath('complaint_performance.summary.clear', 1)
            ->assertJsonPath('complaint_performance.summary.auto_cleared', 1)
            ->assertJsonPath('complaint_performance.rows.0.clear', 1)
            ->assertJsonPath('complaint_performance.rows.0.auto_cleared', 1);

        $this->getJson($base.'&user_id='.$other->id)->assertOk()
            ->assertJsonPath('complaint_performance.summary.clear', 0)
            ->assertJsonPath('complaint_performance.summary.auto_cleared', 0);
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
