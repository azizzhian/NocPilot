<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DailyComplaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplaintClearOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_pembuat_dapat_clear_lewat_patch_dan_put(): void
    {
        $creator = $this->makeUser('Pembuat');
        $complaint = $this->makeComplaint($creator);
        Sanctum::actingAs($creator);

        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Clear'])
            ->assertOk();

        $complaint->refresh();
        $this->assertSame('Clear', $complaint->status);
        $this->assertSame($creator->id, $complaint->cleared_by);
        $this->assertNotNull($complaint->cleared_at);
        $this->assertNotNull($complaint->handling_minutes);
        $this->assertDatabaseHas('activity_logs', [
            'type' => 'complaint',
            'user_id' => $creator->id,
            'user_name' => 'Pembuat',
        ]);

        $second = $this->makeComplaint($creator);
        $this->putJson("/api/v1/daily-entry/complaint/{$second->id}", [
            'report_date' => today()->toDateString(),
            'customer_name' => 'Pelanggan',
            'customer_code' => 'PLG-001',
            'action' => 'Selesai diperbaiki',
            'status' => 'Clear',
        ])->assertOk();

        $second->refresh();
        $this->assertSame('Clear', $second->status);
        $this->assertSame($creator->id, $second->cleared_by);
    }

    public function test_petugas_lain_dan_admin_mendapat_403_di_kedua_endpoint(): void
    {
        $creator = $this->makeUser('Pembuat');
        $other = $this->makeUser('Petugas Lain');
        $admin = $this->makeUser('Admin', true);

        foreach ([$other, $admin] as $intruder) {
            $complaint = $this->makeComplaint($creator);
            Sanctum::actingAs($intruder);

            $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Clear'])
                ->assertForbidden();
            $this->putJson("/api/v1/daily-entry/complaint/{$complaint->id}", [
                'report_date' => today()->toDateString(),
                'customer_name' => 'Pelanggan',
                'customer_code' => 'PLG-001',
                'action' => 'Selesai diperbaiki',
                'status' => 'Clear',
            ])->assertForbidden();

            $complaint->refresh();
            $this->assertSame('On-Progress', $complaint->status);
            $this->assertNull($complaint->cleared_by);
            $this->assertNull($complaint->cleared_at);
        }
    }

    public function test_petugas_lain_tidak_dapat_mengubah_status_apapun(): void
    {
        $creator = $this->makeUser('Pembuat');
        $other = $this->makeUser('Petugas Lain');
        $complaint = $this->makeComplaint($creator, 'Menunggu Konfirmasi');
        Sanctum::actingAs($other);

        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'On-Progress'])
            ->assertForbidden();
        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Clear'])
            ->assertForbidden();
        $this->putJson("/api/v1/daily-entry/complaint/{$complaint->id}", [
            'report_date' => today()->toDateString(),
            'customer_name' => 'Pelanggan',
            'customer_code' => 'PLG-001',
            'action' => 'Selesai diperbaiki',
            'status' => 'On-Progress',
        ])->assertForbidden();

        $this->assertSame('Menunggu Konfirmasi', $complaint->fresh()->status);
    }

    public function test_pembuat_dapat_mengubah_on_progress_dan_menunggu_konfirmasi(): void
    {
        $creator = $this->makeUser('Pembuat');
        $complaint = $this->makeComplaint($creator, 'Menunggu Konfirmasi');
        Sanctum::actingAs($creator);

        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'On-Progress'])
            ->assertOk();
        $this->assertSame('On-Progress', $complaint->fresh()->status);

        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Menunggu Konfirmasi'])
            ->assertOk();
        $this->assertSame('Menunggu Konfirmasi', $complaint->fresh()->status);
    }

    public function test_action_wajib_tetap_berlaku_bagi_pembuat(): void
    {
        $creator = $this->makeUser('Pembuat');
        $complaint = $this->makeComplaint($creator, 'On-Progress', ['action' => null]);
        Sanctum::actingAs($creator);

        $this->patchJson("/api/v1/daily-entry/complaint/{$complaint->id}/status", ['status' => 'Clear'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');
    }

    public function test_auto_clear_mencatat_system_scheduler_bukan_created_by(): void
    {
        $creator = $this->makeUser('Pembuat');
        $complaint = $this->makeComplaint($creator, 'Menunggu Konfirmasi', [
            'handled_at' => now()->subMinutes(31),
            'handled_by' => $creator->id,
            'action' => 'Sudah ditangani',
        ]);

        $this->artisan('complaint:auto-clear')->assertSuccessful();

        $complaint->refresh();
        $this->assertSame('Clear', $complaint->status);
        $this->assertNull($complaint->cleared_by);
        $this->assertNotNull($complaint->cleared_at);
        $this->assertTrue((bool) $complaint->auto_cleared);
        $this->assertNotNull($complaint->handling_minutes);

        $this->assertDatabaseHas('activity_logs', [
            'type' => 'complaint',
            'subject_id' => $complaint->id,
            'user_id' => null,
            'user_name' => 'System / Scheduler',
        ]);
        $log = ActivityLog::where('subject_id', $complaint->id)->firstOrFail();
        $this->assertSame('scheduler', $log->meta['source'] ?? null);
        $this->assertStringContainsString('System / Scheduler', $log->action);

        Sanctum::actingAs($creator);
        $this->getJson('/api/v1/daily-entry/list/complaints?from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.0.clearer_name', 'System / Scheduler');
    }

    public function test_non_pembuat_tidak_dapat_edit_dan_hapus_komplain(): void
    {
        $creator = $this->makeUser('Pembuat');
        $other = $this->makeUser('Petugas Lain');
        $admin = $this->makeUser('Admin', true);

        foreach ([$other, $admin] as $intruder) {
            $complaint = $this->makeComplaint($creator);
            Sanctum::actingAs($intruder);

            $this->putJson("/api/v1/daily-entry/complaint/{$complaint->id}", [
                'report_date' => today()->toDateString(),
                'customer_name' => 'Pelanggan',
                'customer_code' => 'PLG-001',
                'problem' => 'Diubah petugas lain',
                'action' => 'Sudah ditangani',
                'status' => 'On-Progress',
            ])->assertForbidden();
            $this->deleteJson("/api/v1/daily-entry/complaint/{$complaint->id}")
                ->assertForbidden();

            $this->assertDatabaseHas('daily_complaints', [
                'id' => $complaint->id,
                'problem' => null,
                'status' => 'On-Progress',
            ]);
        }
    }

    public function test_pembuat_dapat_edit_dan_hapus_komplain(): void
    {
        $creator = $this->makeUser('Pembuat');
        $complaint = $this->makeComplaint($creator);
        Sanctum::actingAs($creator);

        $this->putJson("/api/v1/daily-entry/complaint/{$complaint->id}", [
            'report_date' => today()->toDateString(),
            'customer_name' => 'Pelanggan',
            'customer_code' => 'PLG-001',
            'problem' => 'Diperbarui pembuat',
            'action' => 'Sudah ditangani',
            'status' => 'On-Progress',
        ])->assertOk();
        $this->assertDatabaseHas('daily_complaints', [
            'id' => $complaint->id,
            'problem' => 'Diperbarui pembuat',
        ]);

        $this->deleteJson("/api/v1/daily-entry/complaint/{$complaint->id}")->assertOk();
        $this->assertDatabaseMissing('daily_complaints', ['id' => $complaint->id]);
    }

    protected function makeUser(string $name, bool $admin = false): User
    {
        $user = User::factory()->create(['name' => $name]);
        if ($admin) {
            $user->assignRole(Role::firstOrCreate([
                'name' => 'administrator',
                'guard_name' => 'web',
            ]));
        }

        return $user;
    }

    /** @param array<string, mixed> $extra */
    protected function makeComplaint(User $creator, string $status = 'On-Progress', array $extra = []): DailyComplaint
    {
        $complaint = DailyComplaint::create(array_merge([
            'report_date' => today()->toDateString(),
            'customer_name' => 'Pelanggan',
            'status' => $status,
            'created_by' => $creator->id,
            'action' => 'Sudah ditangani',
        ], $extra));
        DB::table('daily_complaints')->where('id', $complaint->id)
            ->update([
                'report_date' => today()->toDateString(),
                'created_at' => now()->subMinutes(10),
            ]);

        return $complaint->refresh();
    }
}
