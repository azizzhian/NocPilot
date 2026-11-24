<?php

namespace Tests\Feature;

use App\Models\DailyComplaint;
use App\Models\ReportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardPerformanceClearLikeTest extends TestCase
{
    use RefreshDatabase;

    protected function dashboardUrl(): string
    {
        $date = today()->toDateString();

        return "/api/v1/dashboard/stats?period=custom&from={$date}&to={$date}";
    }

    protected function makeComplaint(User $creator, array $overrides = []): DailyComplaint
    {
        $complaint = DailyComplaint::create(array_merge([
            'report_date' => today()->toDateString(),
            'complaint_type' => DailyComplaint::TYPE_INDIVIDUAL,
            'customer_name' => 'Pelanggan Uji',
            'odc_name' => 'ODC UJI',
            'problem' => 'Internet mati',
            'action' => 'Sudah dicek remote',
            'status' => 'On-Progress',
            'created_by' => $creator->id,
        ], $overrides));

        DB::table('daily_complaints')->where('id', $complaint->id)
            ->update(['report_date' => today()->toDateString()]);

        return $complaint->refresh();
    }

    protected function grantTicketPermission(User $user): void
    {
        foreach (['ticket.view', 'ticket.manage'] as $name) {
            Permission::findOrCreate($name);
        }
        $user->givePermissionTo(['ticket.view', 'ticket.manage']);
        Sanctum::actingAs($user);
    }

    protected function escalate(User $actor, DailyComplaint $complaint): ReportTicket
    {
        $this->grantTicketPermission($actor);

        $this->postJson('/api/v1/report-tickets', [
            'complaint_id' => $complaint->id,
            'escalation_reason' => 'Membutuhkan kunjungan teknisi.',
            'status' => 'On-Progress',
        ])->assertCreated();

        return ReportTicket::firstOrFail();
    }

    public function test_menunggu_konfirmasi_lewat_30_menit_dihitung_clear_di_semua_tampilan_performa(): void
    {
        $creator = User::factory()->create(['name' => 'NOC Input']);
        $handler = User::factory()->create(['name' => 'NOC Selesai']);
        Sanctum::actingAs($creator);

        $this->makeComplaint($creator, [
            'status' => 'Menunggu Konfirmasi',
            'handled_at' => now()->subMinutes(31),
            'handled_by' => $handler->id,
        ]);

        $response = $this->getJson($this->dashboardUrl())->assertOk();
        $response->assertJsonPath('summary.complaints_clear', 1)
            ->assertJsonPath('summary.complaints_open', 0)
            ->assertJsonPath('summary.complaints_open_all', 0)
            ->assertJsonPath('charts.stacked_by_noc.series.0.data.0', 1)
            ->assertJsonPath('kpis.0.clear', 1)
            ->assertJsonPath('complaint_performance.summary.clear', 1)
            ->assertJsonPath('complaint_performance.summary.clear_final', 0)
            ->assertJsonPath('complaint_performance.summary.waiting', 1)
            ->assertJsonPath('complaint_performance.summary.open', 0);

        $odc = $response->json('odc_stats.0');
        $this->assertSame(1, (int) $odc['complaints_clear']);
        $this->assertSame(0, (int) $odc['complaints_open']);

        $rows = collect($response->json('noc_performance'))
            ->firstWhere('name', 'NOC Selesai');
        $this->assertNotNull($rows);
        $this->assertSame(1, (int) $rows['complaints_clear']);
        $this->assertSame(0, (int) $rows['complaints_open']);
    }

    public function test_menunggu_konfirmasi_belum_30_menit_masih_dihitung_open(): void
    {
        $creator = User::factory()->create(['name' => 'NOC Input']);
        $handler = User::factory()->create(['name' => 'NOC Selesai']);
        Sanctum::actingAs($creator);

        $this->makeComplaint($creator, [
            'status' => 'Menunggu Konfirmasi',
            'handled_at' => now(),
            'handled_by' => $handler->id,
        ]);

        $response = $this->getJson($this->dashboardUrl())->assertOk();
        $response->assertJsonPath('summary.complaints_clear', 0)
            ->assertJsonPath('summary.complaints_open', 1)
            ->assertJsonPath('summary.complaints_open_all', 1)
            ->assertJsonPath('charts.stacked_by_noc.series.0.data.0', 1)
            ->assertJsonPath('kpis.0.clear', 0)
            ->assertJsonPath('complaint_performance.summary.clear', 0)
            ->assertJsonPath('complaint_performance.summary.waiting', 1)
            ->assertJsonPath('complaint_performance.summary.open', 1);

        $odc = $response->json('odc_stats.0');
        $this->assertSame(0, (int) $odc['complaints_clear']);
        $this->assertSame(1, (int) $odc['complaints_open']);

        $rows = collect($response->json('noc_performance'))
            ->firstWhere('name', 'NOC Input');
        $this->assertNotNull($rows);
        $this->assertSame(0, (int) $rows['complaints_clear']);
        $this->assertSame(1, (int) $rows['complaints_open']);
    }

    public function test_menunggu_konfirmasi_tanpa_handled_at_tetap_dihitung_clear(): void
    {
        $creator = User::factory()->create(['name' => 'NOC Legacy']);
        Sanctum::actingAs($creator);

        $this->makeComplaint($creator, [
            'status' => 'Menunggu Konfirmasi',
        ]);

        $this->getJson($this->dashboardUrl())->assertOk()
            ->assertJsonPath('summary.complaints_clear', 1)
            ->assertJsonPath('summary.complaints_open', 0)
            ->assertJsonPath('complaint_performance.summary.clear', 1)
            ->assertJsonPath('complaint_performance.summary.open', 0);
    }

    public function test_eskalasi_tiket_dibaca_clear_dan_atribut_ke_pembuat_komplain(): void
    {
        $creator = User::factory()->create(['name' => 'NOC Eskalasi']);
        $complaint = $this->makeComplaint($creator);
        $this->escalate($creator, $complaint);

        $response = $this->getJson($this->dashboardUrl())->assertOk();
        $response->assertJsonPath('summary.complaints_clear', 1)
            ->assertJsonPath('summary.complaints_open', 0)
            ->assertJsonPath('complaint_performance.summary.clear', 1)
            ->assertJsonPath('complaint_performance.summary.clear_final', 0)
            ->assertJsonPath('complaint_performance.summary.escalated', 1)
            ->assertJsonPath('complaint_performance.summary.open', 0);

        $rows = $response->json('noc_performance');
        $this->assertCount(1, $rows);
        $this->assertSame($creator->id, (int) $rows[0]['user_id']);
        $this->assertSame(1, (int) $rows[0]['complaints_clear']);
    }

    public function test_clear_final_dan_sla_tetap_hanya_dari_komplain_clear_final(): void
    {
        $creator = User::factory()->create(['name' => 'NOC Clear']);
        Sanctum::actingAs($creator);

        $this->makeComplaint($creator, [
            'status' => 'Clear',
            'action' => 'Selesai',
            'cleared_by' => $creator->id,
            'cleared_at' => now(),
            'handling_minutes' => 20,
            'performance_category' => 'Cepat',
        ]);
        $this->makeComplaint($creator, [
            'status' => 'Menunggu Konfirmasi',
            'handled_at' => now()->subMinutes(31),
            'handled_by' => $creator->id,
        ]);

        $this->getJson($this->dashboardUrl())->assertOk()
            ->assertJsonPath('summary.complaints_clear', 2)
            ->assertJsonPath('complaint_performance.summary.clear', 2)
            ->assertJsonPath('complaint_performance.summary.clear_final', 1)
            ->assertJsonPath('complaint_performance.summary.sla.clear', 1)
            ->assertJsonPath('complaint_performance.summary.categories.Cepat', 1)
            ->assertJsonPath('complaint_performance.rows.0.clear', 2)
            ->assertJsonPath('complaint_performance.rows.0.clear_final', 1);
    }

    public function test_tiket_dibatalkan_mengembalikan_komplain_ke_open(): void
    {
        $creator = User::factory()->create(['name' => 'NOC Batal']);
        $complaint = $this->makeComplaint($creator);
        $ticket = $this->escalate($creator, $complaint);

        $this->patchJson("/api/v1/report-tickets/{$ticket->id}/status", ['status' => 'Cancelled'])
            ->assertOk();

        $this->assertNull($complaint->fresh()->escalated_at);
        $this->getJson($this->dashboardUrl())->assertOk()
            ->assertJsonPath('summary.complaints_clear', 0)
            ->assertJsonPath('summary.complaints_open', 1)
            ->assertJsonPath('complaint_performance.summary.clear', 0)
            ->assertJsonPath('complaint_performance.summary.open', 1);
    }
}
