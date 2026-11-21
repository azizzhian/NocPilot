<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DailyComplaint;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplaintClearMinimumAgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_clear_diblokir_sebelum_5_menit_di_patch_dan_put(): void
    {
        $creator = $this->makeUser('Pembuat');
        Sanctum::actingAs($creator);

        foreach ([1, 4] as $minutes) {
            $complaint = $this->makeComplaint($creator, now()->subMinutes($minutes));

            $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Clear'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('created_at');
            $this->assertSame('On-Progress', $complaint->fresh()->status);

            $this->putJson("/api/v1/daily-entry/complaint/{$complaint->id}", $this->payload('Clear'))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('created_at');
            $this->assertSame('On-Progress', $complaint->fresh()->status);
        }
    }

    public function test_batas_presisi_detik_4_menit_59_detik_ditolak_dan_tepat_5_menit_diizinkan(): void
    {
        $creator = $this->makeUser('Pembuat');
        Sanctum::actingAs($creator);

        $young = $this->makeComplaint($creator, now()->subSeconds(299));
        $this->patchJson("/api/v1/daily-entry/complaint/{$young->id}/status", ['status' => 'Clear'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('created_at')
            ->assertJsonPath(
                'errors.created_at.0',
                'Komplain belum dapat di-Clear. Minimal waktu penanganan adalah 5 menit.',
            );
        $this->assertSame('On-Progress', $young->fresh()->status);

        $ready = $this->makeComplaint($creator, now()->subSeconds(300));
        $this->patchJson("/api/v1/daily-entry/complaint/{$ready->id}/status", ['status' => 'Clear'])
            ->assertOk();
        $this->assertSame('Clear', $ready->fresh()->status);
    }

    public function test_clear_setelah_10_menit_diizinkan(): void
    {
        $creator = $this->makeUser('Pembuat');
        $complaint = $this->makeComplaint($creator, now()->subMinutes(10));
        Sanctum::actingAs($creator);

        $this->putJson("/api/v1/daily-entry/complaint/{$complaint->id}", $this->payload('Clear'))
            ->assertOk();

        $complaint->refresh();
        $this->assertSame('Clear', $complaint->status);
        $this->assertSame($creator->id, $complaint->cleared_by);
        $this->assertNotNull($complaint->cleared_at);
        $this->assertNotNull($complaint->handling_minutes);
    }

    public function test_created_at_kosong_atau_tidak_valid_ditolak(): void
    {
        $creator = $this->makeUser('Pembuat');
        Sanctum::actingAs($creator);

        foreach ([null, 'bukan-tanggal'] as $invalid) {
            $complaint = $this->makeComplaint($creator);
            DB::table('daily_complaints')->where('id', $complaint->id)
                ->update(['created_at' => $invalid]);

            $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Clear'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('created_at')
                ->assertJsonPath(
                    'errors.created_at.0',
                    'Waktu input komplain tidak ditemukan atau tidak valid. Komplain belum dapat di-Clear.',
                );
            $this->assertSame('On-Progress', $complaint->fresh()->status);
        }
    }

    public function test_non_pembuat_tetap_403_walaupun_komplain_muda(): void
    {
        $creator = $this->makeUser('Pembuat');
        $other = $this->makeUser('Petugas Lain');
        $complaint = $this->makeComplaint($creator, now()->subMinute());
        Sanctum::actingAs($other);

        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Clear'])
            ->assertForbidden();
        $this->putJson("/api/v1/daily-entry/complaint/{$complaint->id}", $this->payload('Clear'))
            ->assertForbidden();
        $this->assertSame('On-Progress', $complaint->fresh()->status);
    }

    public function test_action_kosong_tetap_error_action_lebih_dulu(): void
    {
        $creator = $this->makeUser('Pembuat');
        $complaint = $this->makeComplaint($creator, now()->subMinutes(10), 'On-Progress', ['action' => null]);
        Sanctum::actingAs($creator);

        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Clear'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');
    }

    public function test_store_komplain_tidak_boleh_langsung_clear(): void
    {
        $creator = $this->makeUser('Pembuat');
        Sanctum::actingAs($creator);

        $this->postJson('/api/v1/daily-entry/complaint', $this->payload('Clear'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status')
            ->assertJsonPath(
                'errors.status.0',
                'Komplain baru tidak dapat langsung di-Clear. Minimal waktu penanganan adalah 5 menit.',
            );
        $this->assertDatabaseCount('daily_complaints', 0);

        $this->postJson('/api/v1/daily-entry/complaint', $this->payload('On-Progress', null))
            ->assertCreated();
        $this->assertDatabaseCount('daily_complaints', 1);
        $this->assertSame('On-Progress', DailyComplaint::firstOrFail()->status);
    }

    public function test_edit_komplain_sudah_clear_tidak_terkena_validasi_umur(): void
    {
        $creator = $this->makeUser('Pembuat');
        $complaint = $this->makeComplaint($creator, now(), 'Clear');
        Sanctum::actingAs($creator);

        $this->putJson("/api/v1/daily-entry/complaint/{$complaint->id}", $this->payload('Clear', 'Tindak lanjut'))
            ->assertOk();
        $this->assertSame('Clear', $complaint->fresh()->status);
    }

    public function test_auto_clear_tetap_berjalan_walaupun_komplain_muda(): void
    {
        $creator = $this->makeUser('Pembuat');
        $complaint = $this->makeComplaint($creator, now(), 'Menunggu Konfirmasi', [
            'handled_at' => now()->subMinutes(31),
            'handled_by' => $creator->id,
        ]);

        $this->artisan('complaint:auto-clear')->assertSuccessful();

        $complaint->refresh();
        $this->assertSame('Clear', $complaint->status);
        $this->assertNull($complaint->cleared_by);
        $this->assertTrue((bool) $complaint->auto_cleared);
        $this->assertDatabaseHas('activity_logs', [
            'type' => 'complaint',
            'subject_id' => $complaint->id,
            'user_name' => 'System / Scheduler',
        ]);
        $this->assertSame(
            'scheduler',
            ActivityLog::where('subject_id', $complaint->id)->firstOrFail()->meta['source'] ?? null,
        );
    }

    public function test_transisi_non_clear_tidak_terkena_validasi_umur(): void
    {
        $creator = $this->makeUser('Pembuat');
        $complaint = $this->makeComplaint($creator, now()->subMinute());
        Sanctum::actingAs($creator);

        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Menunggu Konfirmasi'])
            ->assertOk();
        $this->assertSame('Menunggu Konfirmasi', $complaint->fresh()->status);

        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'On-Progress'])
            ->assertOk();
        $this->assertSame('On-Progress', $complaint->fresh()->status);
    }

    protected function makeUser(string $name): User
    {
        return User::factory()->create(['name' => $name]);
    }

    protected function makeComplaint(
        User $creator,
        ?CarbonInterface $createdAt = null,
        string $status = 'On-Progress',
        array $extra = [],
    ): DailyComplaint {
        $complaint = DailyComplaint::create(array_merge([
            'report_date' => today()->toDateString(),
            'customer_name' => 'Pelanggan',
            'customer_code' => 'PLG-001',
            'status' => $status,
            'created_by' => $creator->id,
            'action' => 'Sudah ditangani',
        ], $extra));

        DB::table('daily_complaints')->where('id', $complaint->id)->update([
            'report_date' => today()->toDateString(),
            'created_at' => ($createdAt ?? now()->subMinutes(10))->toDateTimeString(),
        ]);

        return $complaint->refresh();
    }

    /** @return array<string, mixed> */
    protected function payload(string $status, ?string $action = 'Sudah ditangani'): array
    {
        return [
            'report_date' => today()->toDateString(),
            'customer_name' => 'Pelanggan',
            'customer_code' => 'PLG-001',
            'action' => $action,
            'status' => $status,
        ];
    }
}
