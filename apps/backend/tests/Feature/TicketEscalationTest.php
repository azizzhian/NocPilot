<?php

namespace Tests\Feature;

use App\Models\DailyComplaint;
use App\Models\ReportTicket;
use App\Models\ReportTicketUpdate;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TicketEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-23 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tiket_langsung_dibuat_tanpa_komplain_dan_complaint_id_null(): void
    {
        $user = $this->actor();

        $response = $this->postJson('/api/v1/report-tickets', [
            'customer_name' => 'Pelanggan Langsung',
            'customer_code' => 'PLG-1',
            'problem' => 'Lambat',
            'status' => 'On-Progress',
            'opened_at' => '2026-11-23',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.source', 'direct')
            ->assertJsonPath('data.complaint_id', null)
            ->assertJsonPath('data.customer_name', 'Pelanggan Langsung');

        $ticket = ReportTicket::firstOrFail();
        $this->assertNull($ticket->complaint_id);
        $this->assertSame('direct', $ticket->source);
        $this->assertSame($user->id, $ticket->created_by);
        $this->assertDatabaseCount('daily_complaints', 0);
    }

    public function test_tiket_langsung_assignment_update_dan_selesai(): void
    {
        $user = $this->actor();
        $teknisi = User::factory()->create(['name' => 'Teknisi Lapangan']);
        $ticket = $this->makeTicket($user);

        $this->postJson("/api/v1/report-tickets/{$ticket->id}/assign", ['assigned_to' => $teknisi->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'Assigned')
            ->assertJsonPath('data.assignee_name', 'Teknisi Lapangan');

        $this->postJson("/api/v1/report-tickets/{$ticket->id}/start", ['note' => 'Berangkat ke lokasi'])
            ->assertOk()
            ->assertJsonPath('data.status', 'In-Progress');

        Storage::fake('public');
        $this->postJson("/api/v1/report-tickets/{$ticket->id}/updates", [
            'type' => 'progress',
            'check_result' => 'Kabel dropcore putus',
            'repair_action' => 'Sambung ulang konektor',
            'material' => 'Konektor RJ45',
            'note' => 'Selesai pengecekan',
        ])->assertCreated()->assertJsonPath('data.check_result', 'Kabel dropcore putus');

        $this->assertDatabaseHas('report_ticket_updates', [
            'report_ticket_id' => $ticket->id,
            'type' => 'progress',
            'repair_action' => 'Sambung ulang konektor',
            'created_by' => $user->id,
        ]);

        $this->getJson("/api/v1/report-tickets/{$ticket->id}/updates")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->patchJson("/api/v1/report-tickets/{$ticket->id}/status", [
            'status' => 'Close',
            'action' => 'Kabel dropcore disambung ulang.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'Closed')
            ->assertJsonPath('data.status_label', 'Close');

        $ticket->refresh();
        $this->assertNotNull($ticket->cleared_at);
        $this->assertNotNull($ticket->closed_at);
        $this->assertNotNull($ticket->work_completed_at);
        $this->getJson('/api/v1/report-tickets/export?from=2026-11-01&to=2026-11-30')->assertOk();
    }

    public function test_eskalasi_mengisi_data_dari_komplain_dan_relasi_dua_arah(): void
    {
        $user = $this->actor();
        $complaint = $this->makeComplaint($user);
        DB::table('daily_complaints')->where('id', $complaint->id)->update([
            'created_at' => now()->subMinutes(8)->toDateTimeString(),
            'updated_at' => now()->subMinutes(8)->toDateTimeString(),
        ]);
        $complaint->refresh();

        $response = $this->postJson('/api/v1/report-tickets', [
            'complaint_id' => $complaint->id,
            'escalation_reason' => 'Gangguan tidak dapat diselesaikan melalui remote troubleshooting.',
            'job_type' => 'Pengecekan kabel dropcore',
            'priority' => 'high',
            'status' => 'Open',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.source', 'complaint')
            ->assertJsonPath('data.complaint_id', $complaint->id)
            ->assertJsonPath('data.customer_name', 'Pelanggan Uji')
            ->assertJsonPath('data.customer_code', 'CUST-1')
            ->assertJsonPath('data.customer_phone', '081234567890')
            ->assertJsonPath('data.problem', 'Internet mati')
            ->assertJsonPath('data.odc_name', 'ODC UJI');

        $ticket = ReportTicket::firstOrFail();
        $this->assertSame('complaint', $ticket->source);
        $this->assertSame($complaint->id, $ticket->complaint_id);
        $this->assertNotNull($ticket->escalated_at);
        $this->assertSame($complaint->complaintNumber(), $ticket->source_complaint_number);
        $this->assertSame('high', $ticket->priority);
        $this->assertSame(
            'Gangguan tidak dapat diselesaikan melalui remote troubleshooting.',
            $ticket->source_snapshot['escalation_reason'],
        );

        $complaint->refresh();
        $this->assertSame($ticket->id, $complaint->ticket_id);
        $this->assertTrue($complaint->hasActiveTicket());
        $this->assertSame('On-Progress', $complaint->status);

        $this->assertDatabaseHas('activity_logs', [
            'type' => 'complaint',
            'subject_type' => DailyComplaint::class,
            'subject_id' => $complaint->id,
        ]);
        $this->assertDatabaseHas('report_ticket_updates', [
            'report_ticket_id' => $ticket->id,
            'type' => 'created',
        ]);
    }

    public function test_alasan_eskalasi_wajib_dan_komplain_clear_tidak_bisa_dieskalasi(): void
    {
        $this->actor();
        $complaint = $this->makeComplaint($this->actor());

        $this->postJson('/api/v1/report-tickets', [
            'complaint_id' => $complaint->id,
            'status' => 'Open',
        ])->assertUnprocessable()->assertJsonValidationErrors('escalation_reason');

        DB::table('daily_complaints')->where('id', $complaint->id)->update(['status' => 'Clear']);
        $this->postJson('/api/v1/report-tickets', [
            'complaint_id' => $complaint->id,
            'escalation_reason' => 'Perlu kunjungan teknisi.',
            'status' => 'Open',
        ])->assertUnprocessable()->assertJsonValidationErrors('complaint_id');
    }

    public function test_eskalasi_hanya_bisa_dilakukan_oleh_pembuat_komplain(): void
    {
        $owner = $this->actor();
        $complaint = $this->makeComplaint($owner);
        $payload = [
            'complaint_id' => $complaint->id,
            'escalation_reason' => 'Membutuhkan pengecekan lapangan.',
            'status' => 'Open',
        ];

        $other = User::factory()->create(['name' => 'NOC Lain']);
        foreach (['ticket.view', 'ticket.manage'] as $name) {
            Permission::findOrCreate($name);
        }
        $other->givePermissionTo(['ticket.view', 'ticket.manage']);
        Sanctum::actingAs($other);

        $this->postJson('/api/v1/report-tickets', $payload)->assertForbidden();
        $this->assertSame(0, ReportTicket::count());

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/report-tickets', $payload)->assertCreated();
        $this->assertSame(1, ReportTicket::count());
    }

    public function test_satu_komplain_tidak_bisa_mempunyai_dua_tiket_aktif(): void
    {
        $user = $this->actor();
        $complaint = $this->makeComplaint($user);
        $payload = [
            'complaint_id' => $complaint->id,
            'escalation_reason' => 'Butuh pengecekan lapangan.',
            'status' => 'Open',
        ];

        $this->postJson('/api/v1/report-tickets', $payload)->assertCreated();

        $this->postJson('/api/v1/report-tickets', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('complaint_id');
        $this->assertSame(1, ReportTicket::where('complaint_id', $complaint->id)->count());

        $ticket = ReportTicket::firstOrFail();
        $this->patchJson("/api/v1/report-tickets/{$ticket->id}/status", ['status' => 'Cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Cancelled');

        $complaint->refresh();
        $this->assertNull($complaint->escalated_at);
        $this->assertFalse($complaint->hasActiveTicket());
        $this->postJson('/api/v1/report-tickets', $payload)->assertCreated();
        $this->assertSame(2, ReportTicket::where('complaint_id', $complaint->id)->count());
    }

    public function test_tiket_aktif_melindungi_komplain_dari_auto_clear_dan_timer_dimulai_ulang(): void
    {
        $user = $this->actor();
        $complaint = $this->makeComplaint($user, 'Menunggu Konfirmasi');
        DB::table('daily_complaints')->where('id', $complaint->id)->update([
            'handled_at' => now()->subMinutes(31)->toDateTimeString(),
        ]);

        $ticketResponse = $this->postJson('/api/v1/report-tickets', [
            'complaint_id' => $complaint->id,
            'escalation_reason' => 'Membutuhkan pengecekan jalur FO.',
            'status' => 'Assigned',
            'assigned_to' => $user->id,
        ])->assertCreated();
        $ticketId = $ticketResponse->json('data.id');

        $this->artisan('complaint:auto-clear')->assertSuccessful();
        $this->assertSame('Menunggu Konfirmasi', $complaint->fresh()->status);

        $this->patchJson("/api/v1/report-tickets/{$ticketId}/status", ['status' => 'Completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Completed');

        $complaint->refresh();
        $this->assertSame('Menunggu Konfirmasi', $complaint->status);
        $this->assertNull($complaint->cleared_at);
        $this->assertTrue($complaint->handled_at->equalTo(now()));

        $this->artisan('complaint:auto-clear')->assertSuccessful();
        $this->assertSame('Menunggu Konfirmasi', $complaint->fresh()->status);

        Carbon::setTestNow(now()->addMinutes(31));
        $this->artisan('complaint:auto-clear')->assertSuccessful();
        $complaint->refresh();
        $this->assertSame('Clear', $complaint->status);
        $this->assertTrue((bool) $complaint->auto_cleared);
    }

    public function test_selesai_tiket_tidak_auto_clear_dan_aturan_clear_manual_tetap_berlaku(): void
    {
        $user = $this->actor();
        $complaint = $this->makeComplaint($user);
        DB::table('daily_complaints')->where('id', $complaint->id)->update([
            'created_at' => now()->subMinutes(2)->toDateTimeString(),
        ]);
        $ticket = $this->createEscalationTicket($user, $complaint);

        $this->patchJson("/api/v1/report-tickets/{$ticket->id}/status", ['status' => 'Completed'])->assertOk();
        $complaint->refresh();
        $this->assertSame('Menunggu Konfirmasi', $complaint->status);
        $this->assertNull($complaint->cleared_at);

        DB::table('daily_complaints')->where('id', $complaint->id)->update(['action' => null]);
        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Clear'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');

        DB::table('daily_complaints')->where('id', $complaint->id)->update(['action' => 'Sudah dikerjakan teknisi.']);
        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Clear'])
            ->assertUnprocessable()->assertJsonValidationErrors('created_at');

        DB::table('daily_complaints')->where('id', $complaint->id)->update([
            'created_at' => now()->subMinutes(10)->toDateTimeString(),
        ]);
        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", [
            'status' => 'Clear',
            'action' => 'Sudah dikerjakan teknisi.',
        ])->assertOk();

        $complaint->refresh();
        $this->assertSame('Clear', $complaint->status);
        $this->assertSame($user->id, $complaint->cleared_by);
        $this->assertFalse((bool) $complaint->auto_cleared);
    }

    public function test_riwayat_komplain_dan_riwayat_tiket_tidak_saling_menimpa(): void
    {
        $user = $this->actor();
        $complaint = $this->makeComplaint($user);
        $ticket = $this->createEscalationTicket($user, $complaint);

        $complaintLogsBefore = DB::table('activity_logs')
            ->where('type', 'complaint')
            ->where('subject_id', $complaint->id)
            ->count();
        $this->assertGreaterThan(0, $complaintLogsBefore);

        $this->postJson("/api/v1/report-tickets/{$ticket->id}/updates", [
            'check_result' => 'Modem tidak menyala',
            'repair_action' => 'Ganti ONU',
        ])->assertCreated();

        $this->assertDatabaseCount('report_ticket_updates', 2);
        $this->assertSame(
            $complaintLogsBefore,
            DB::table('activity_logs')->where('type', 'complaint')->where('subject_id', $complaint->id)->count(),
        );
        $this->assertDatabaseHas('activity_logs', [
            'type' => 'ticket',
            'subject_type' => ReportTicket::class,
            'subject_id' => $ticket->id,
        ]);
    }

    public function test_hapus_komplain_tidak_menghapus_tiket_dan_snapshot_tetap_terbaca(): void
    {
        $user = $this->actor();
        $complaint = $this->makeComplaint($user);
        $ticket = $this->createEscalationTicket($user, $complaint);

        $this->deleteJson("/api/v1/daily-entry/complaint/{$complaint->id}")->assertOk();

        $ticket->refresh();
        $this->assertNull($ticket->complaint_id);
        $this->assertSame('complaint', $ticket->source);
        $this->assertSame($complaint->complaintNumber(), $ticket->source_snapshot['complaint_number']);
        $this->assertDatabaseHas('report_tickets', ['id' => $ticket->id]);
        $this->assertDatabaseMissing('daily_complaints', ['id' => $complaint->id]);
    }

    public function test_query_distribusi_status_tidak_memakai_order_by(): void
    {
        $user = $this->actor();
        $this->makeTicket($user);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/report-tickets/stats')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->map(fn ($sql) => strtolower($sql));
        DB::disableQueryLog();

        $grouped = $queries->first(fn ($sql) => str_contains($sql, 'group by') && str_contains($sql, 'status'));
        $this->assertNotNull($grouped, 'Query distribusi status tidak ditemukan.');
        $this->assertStringNotContainsString('order by', $grouped, 'GROUP BY status tidak boleh dibarengi ORDER BY id (ONLY_FULL_GROUP_BY).');
    }

    public function test_tiket_baru_default_on_progress_dan_close_mewajibkan_action(): void
    {
        $user = $this->actor();

        $default = $this->postJson('/api/v1/report-tickets', [
            'customer_name' => 'Tanpa Status',
        ])->assertCreated();
        $this->assertSame('On-Progress', $default->json('data.status'));

        $this->postJson('/api/v1/report-tickets', [
            'customer_name' => 'Close Tanpa Action',
            'status' => 'Close',
        ])->assertUnprocessable()->assertJsonValidationErrors('action');

        $closed = $this->postJson('/api/v1/report-tickets', [
            'customer_name' => 'Close Dengan Action',
            'status' => 'Close',
            'action' => 'Modem/ONU diganti.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'Closed')
            ->assertJsonPath('data.status_label', 'Close');

        $ticket = ReportTicket::findOrFail($closed->json('data.id'));
        $this->assertNotNull($ticket->cleared_at);
        $this->assertNotNull($ticket->closed_at);
        $this->assertSame($user->id, $ticket->cleared_by);
    }

    public function test_close_pada_tiket_eskalasi_membuat_komplain_menunggu_konfirmasi(): void
    {
        $user = $this->actor();
        $complaint = $this->makeComplaint($user);

        $response = $this->postJson('/api/v1/report-tickets', [
            'complaint_id' => $complaint->id,
            'escalation_reason' => 'Membutuhkan kunjungan teknisi.',
            'status' => 'Close',
            'action' => 'Konektor splitter diganti.',
        ])->assertCreated();

        $complaint->refresh();
        $this->assertSame('Menunggu Konfirmasi', $complaint->status);
        $this->assertNull($complaint->cleared_at);
        $this->assertNotNull($complaint->handled_at);
        $this->assertSame($response->json('data.id'), $complaint->ticket_id);
    }

    public function test_filter_close_menampilkan_status_clear_dan_closed(): void
    {
        $user = $this->actor();
        $clear = $this->makeTicket($user);
        $clear->status = 'Clear';
        $clear->action = 'Clear historis.';
        $clear->save();
        $closed = $this->makeTicket($user);
        $closed->status = 'Closed';
        $closed->action = 'Close baru.';
        $closed->save();
        $this->makeTicket($user);

        $list = $this->getJson('/api/v1/report-tickets?status=Close')->assertOk()->json('data');
        $this->assertCount(2, $list);
        $this->assertEqualsCanonicalizing(['Clear', 'Closed'], array_column($list, 'status'));
        $this->assertEqualsCanonicalizing(['Clear', 'Close'], array_column($list, 'status_label'));
    }

    public function test_definisi_kpi_memisahkan_status_bisnis_yang_berbeda(): void
    {
        $user = $this->actor();
        foreach (['Open', 'Assigned', 'On-Progress', 'Completed', 'Clear', 'Closed', 'Cancelled'] as $status) {
            ReportTicket::create([
                'customer_name' => 'Pelanggan '.$status,
                'status' => $status,
                'source' => 'direct',
                'opened_at' => today(),
                'created_by' => $user->id,
            ]);
        }

        $stats = $this->getJson('/api/v1/report-tickets/stats')->assertOk()->json();

        $this->assertSame(7, $stats['total']);
        $this->assertSame(1, $stats['active']);
        $this->assertSame(2, $stats['finished']);
        $this->assertSame(2, $stats['close_count']);
        $this->assertSame(1, $stats['status_counts']['Cancelled']);
        $this->assertSame(1, $stats['status_counts']['Completed']);
        $this->assertSame(1, $stats['status_counts']['Clear']);
        $this->assertSame(1, $stats['status_counts']['Closed']);
        $this->assertSame(1, $stats['status_counts']['On-Progress']);
        $this->assertSame(array_sum($stats['status_counts']), $stats['total']);
        $this->assertSame(['On-Progress', 'Closed'], $stats['form_options']);

        $closed = $this->getJson('/api/v1/report-tickets/stats?status=Close')->assertOk()->json();
        $this->assertSame(2, $closed['total']);
        $this->assertSame(0, $closed['active']);
        $this->assertSame(2, $closed['finished']);
    }

    public function test_stats_mengikuti_filter_dan_total_sama_dengan_distribusi_status(): void
    {
        $user = $this->actor();
        $complaint = $this->makeComplaint($user);
        $escalated = $this->createEscalationTicket($user, $complaint);
        $direct = $this->makeTicket($user);
        $closed = $this->makeTicket($user);
        $this->patchJson("/api/v1/report-tickets/{$closed->id}/status", [
            'status' => 'Close',
            'action' => 'ONU diganti.',
        ])->assertOk()->assertJsonPath('data.status', 'Closed');

        $all = $this->getJson('/api/v1/report-tickets/stats')->assertOk()->json();
        $this->assertSame(3, $all['total']);
        $this->assertSame(array_sum($all['status_counts']), $all['total']);
        $this->assertSame(1, $all['active']);
        $this->assertSame(1, $all['finished']);
        $this->assertSame(1, $all['from_complaint']);

        $fromComplaint = $this->getJson('/api/v1/report-tickets/stats?source=complaint')->assertOk()->json();
        $this->assertSame(1, $fromComplaint['total']);
        $this->assertSame(1, $fromComplaint['from_complaint']);

        $openOnly = $this->getJson('/api/v1/report-tickets/stats?status=Open')->assertOk()->json();
        $this->assertSame(1, $openOnly['total']);
        $this->assertSame(1, $openOnly['status_counts']['Open']);

        $byNumber = $this->getJson('/api/v1/report-tickets/stats?search=%23'.$direct->id)->assertOk()->json();
        $this->assertSame(1, $byNumber['total']);
        $this->assertSame(1, $byNumber['status_counts']['On-Progress']);
    }

    public function test_dashboard_menampilkan_ringkasan_tiket_dan_komplain_tereskalasi(): void
    {
        $user = $this->actor();
        $complaint = $this->makeComplaint($user);
        $this->createEscalationTicket($user, $complaint);
        $this->makeTicket($user);

        $response = $this->getJson('/api/v1/dashboard/stats?period=custom&from=2026-11-01&to=2026-11-30')
            ->assertOk();

        $response->assertJsonPath('ticket_summary.from_complaint', 1)
            ->assertJsonPath('ticket_summary.direct', 1)
            ->assertJsonPath('ticket_summary.total', 2)
            ->assertJsonPath('complaint_performance.summary.escalated', 1);
    }

    public function test_generate_report_ticket_mendukung_filter_sumber(): void
    {
        $user = $this->actor();
        $complaint = $this->makeComplaint($user);
        $this->createEscalationTicket($user, $complaint);
        $this->makeTicket($user);
        $closed = $this->makeTicket($user);
        $this->patchJson("/api/v1/report-tickets/{$closed->id}/status", [
            'status' => 'Close',
            'action' => 'Pekerjaan selesai.',
        ])->assertOk();

        $payload = [
            'section' => 'ticket',
            'from' => '2026-11-01',
            'to' => '2026-11-30',
        ];

        $all = $this->postJson('/api/v1/reports/generate/section', $payload)
            ->assertOk()->json('text');
        $this->assertStringContainsString('Tiket Langsung', $all);
        $this->assertStringContainsString('Dari Komplain', $all);

        $direct = $this->postJson('/api/v1/reports/generate/section', [...$payload, 'source' => 'direct'])
            ->assertOk()->json('text');
        $this->assertStringContainsString('Tiket Langsung', $direct);
        $this->assertStringNotContainsString('Dari Komplain', $direct);

        $escalated = $this->postJson('/api/v1/reports/generate/section', [...$payload, 'source' => 'complaint'])
            ->assertOk()->json('text');
        $this->assertStringContainsString('Dari Komplain', $escalated);
        $this->assertStringContainsString('No. Komplain Asal: KM-', $escalated);
        $this->assertStringNotContainsString('Sumber: Tiket Langsung', $escalated);

        $closeText = $this->postJson('/api/v1/reports/generate/section', [...$payload, 'status' => 'Close'])
            ->assertOk()->json('text');
        $this->assertStringContainsString('Status: Close', $closeText);
        $this->assertStringNotContainsString('Status: On-Progress', $closeText);
    }

    public function test_auto_clear_tetap_berjalan_untuk_komplain_tanpa_tiket(): void
    {
        $user = $this->actor();
        $complaint = $this->makeComplaint($user, 'Menunggu Konfirmasi');
        DB::table('daily_complaints')->where('id', $complaint->id)->update([
            'handled_at' => now()->subMinutes(31)->toDateTimeString(),
        ]);

        $this->artisan('complaint:auto-clear')->assertSuccessful();

        $complaint->refresh();
        $this->assertSame('Clear', $complaint->status);
        $this->assertTrue((bool) $complaint->auto_cleared);
        $this->assertNull($complaint->cleared_by);
    }

    private function actor(): User
    {
        foreach (['ticket.view', 'ticket.manage'] as $name) {
            Permission::findOrCreate($name);
        }

        $user = User::factory()->create(['name' => 'NOC Uji']);
        $user->givePermissionTo(['ticket.view', 'ticket.manage']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function makeComplaint(User $user, string $status = 'On-Progress'): DailyComplaint
    {
        return DailyComplaint::create([
            'report_date' => today()->toDateString(),
            'complaint_type' => DailyComplaint::TYPE_INDIVIDUAL,
            'customer_name' => 'Pelanggan Uji',
            'customer_code' => 'CUST-1',
            'phone_normalized' => '081234567890',
            'odc_name' => 'ODC UJI',
            'problem' => 'Internet mati',
            'action' => 'Sudah dicek remote',
            'status' => $status,
            'created_by' => $user->id,
        ]);
    }

    private function makeTicket(User $user): ReportTicket
    {
        return ReportTicket::create([
            'customer_name' => 'Pelanggan Langsung',
            'problem' => 'Lambat',
            'status' => 'On-Progress',
            'source' => 'direct',
            'opened_at' => today(),
            'created_by' => $user->id,
        ]);
    }

    private function createEscalationTicket(User $user, DailyComplaint $complaint): ReportTicket
    {
        $response = $this->postJson('/api/v1/report-tickets', [
            'complaint_id' => $complaint->id,
            'escalation_reason' => 'Membutuhkan kunjungan teknisi.',
            'status' => 'Open',
        ]);

        $response->assertCreated();

        return ReportTicket::findOrFail($response->json('data.id'));
    }
}
