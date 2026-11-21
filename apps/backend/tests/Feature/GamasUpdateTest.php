<?php

namespace Tests\Feature;

use App\Models\DailyGamas;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GamasUpdateTest extends TestCase
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

    public function test_store_update_menambah_catatan_tanpa_mengubah_status_dan_action(): void
    {
        $user = User::factory()->create(['name' => 'NOC Satu']);
        Sanctum::actingAs($user);

        $gamas = DailyGamas::create([
            'report_date' => today()->toDateString(),
            'gamas_kind' => 'odp',
            'location_label' => 'ODP Contoh',
            'problem' => 'Link down',
            'action' => 'Cek lapangan',
            'status' => 'On-Progress',
            'created_by' => $user->id,
        ]);

        $response = $this->postJson("/api/v1/gamas/{$gamas->id}/updates", [
            'update_text' => 'Tim lapangan sedang menuju lokasi.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.daily_gamas_id', $gamas->id)
            ->assertJsonPath('data.update_text', 'Tim lapangan sedang menuju lokasi.')
            ->assertJsonPath('data.author_name', 'NOC Satu')
            ->assertJsonPath('data.started_at', null)
            ->assertJsonPath('data.ended_at', null)
            ->assertJsonPath('data.duration_minutes', null);

        $this->assertDatabaseHas('daily_gamas_updates', [
            'daily_gamas_id' => $gamas->id,
            'update_text' => 'Tim lapangan sedang menuju lokasi.',
            'created_by' => $user->id,
        ]);

        $gamas->refresh();
        $this->assertSame('On-Progress', $gamas->status);
        $this->assertSame('Cek lapangan', $gamas->action);
    }

    public function test_updates_diurutkan_terbaru_di_atas(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $gamas = DailyGamas::create([
            'report_date' => today()->toDateString(),
            'gamas_kind' => 'upstream',
            'location_label' => 'Upstream Contoh',
            'status' => 'On-Progress',
            'created_by' => $user->id,
        ]);

        $this->postJson("/api/v1/gamas/{$gamas->id}/updates", ['update_text' => 'Catatan pertama']);
        $this->postJson("/api/v1/gamas/{$gamas->id}/updates", ['update_text' => 'Catatan kedua']);

        $response = $this->getJson("/api/v1/gamas/{$gamas->id}/updates");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.update_text', 'Catatan kedua')
            ->assertJsonPath('data.1.update_text', 'Catatan pertama');
    }

    public function test_riwayat_kosong_dan_update_text_wajib(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $gamas = DailyGamas::create([
            'report_date' => today()->toDateString(),
            'gamas_kind' => 'olt',
            'location_label' => 'OLT Contoh',
            'status' => 'On-Progress',
            'created_by' => $user->id,
        ]);

        $this->getJson("/api/v1/gamas/{$gamas->id}/updates")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/gamas/{$gamas->id}/updates", ['update_text' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('update_text');
    }

    public function test_beberapa_update_menghitung_durasi_tersendiri_dan_akumulasinya(): void
    {
        $user = User::factory()->create(['name' => 'NOC Timeline']);
        Sanctum::actingAs($user);
        $gamas = $this->makeGamas($user, '2026-11-21 08:00:00');

        $this->postJson("/api/v1/gamas/{$gamas->id}/updates", [
            'update_text' => 'Cek ODP selesai',
            'started_at' => '2026-11-21T09:23:00+07:00',
        ])->assertCreated()
            ->assertJsonPath('data.duration_minutes', 37)
            ->assertJsonPath('data.started_at', '2026-11-21T09:23:00+07:00');

        Carbon::setTestNow(Carbon::parse('2026-11-21 10:10:00'));
        $this->postJson("/api/v1/gamas/{$gamas->id}/updates", [
            'update_text' => 'Koordinasi vendor',
            'started_at' => '2026-11-21T10:05:00+07:00',
        ])->assertCreated()->assertJsonPath('data.duration_minutes', 5);

        $this->getJson("/api/v1/gamas/{$gamas->id}/updates")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.update_text', 'Koordinasi vendor')
            ->assertJsonPath('data.0.duration_minutes', 5)
            ->assertJsonPath('data.1.update_text', 'Cek ODP selesai')
            ->assertJsonPath('data.1.duration_minutes', 37);

        $from = today()->toDateString();
        $params = 'from='.$from.'&to='.$from;
        $this->getJson('/api/v1/gamas/stats?'.$params)
            ->assertOk()
            ->assertJsonPath('summary.update_duration_minutes', 42);
        $this->getJson('/api/v1/dashboard/stats?period=custom&'.$params)
            ->assertOk()
            ->assertJsonPath('gamas_summary.summary.update_duration_minutes', 42);
        $this->getJson('/api/v1/gamas?'.$params)
            ->assertOk()
            ->assertJsonPath('data.0.updates_count', 2)
            ->assertJsonPath('data.0.updates_timed_count', 2)
            ->assertJsonPath('data.0.updates_duration_minutes', 42)
            ->assertJsonPath('data.0.last_update_at', '2026-11-21T10:10:00+07:00');
    }

    public function test_update_lama_tanpa_waktu_tidak_mendapat_durasi_rekayasa(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $gamas = $this->makeGamas($user, '2026-11-21 08:00:00');

        $this->postJson("/api/v1/gamas/{$gamas->id}/updates", ['update_text' => 'Catatan lama'])
            ->assertCreated()
            ->assertJsonPath('data.started_at', null)
            ->assertJsonPath('data.ended_at', null)
            ->assertJsonPath('data.duration_minutes', null);

        $this->postJson("/api/v1/gamas/{$gamas->id}/updates", [
            'update_text' => 'Catatan baru',
            'started_at' => '2026-11-21T09:00:00+07:00',
        ])->assertCreated()->assertJsonPath('data.duration_minutes', 60);

        $from = today()->toDateString();
        $this->getJson('/api/v1/gamas/stats?from='.$from.'&to='.$from)
            ->assertOk()
            ->assertJsonPath('summary.update_duration_minutes', 60);
        $this->getJson('/api/v1/gamas?from='.$from.'&to='.$from)
            ->assertOk()
            ->assertJsonPath('data.0.updates_count', 2)
            ->assertJsonPath('data.0.updates_timed_count', 1)
            ->assertJsonPath('data.0.updates_duration_minutes', 60);
    }

    public function test_validasi_rentang_waktu_update(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $gamas = $this->makeGamas($user, '2026-11-21 08:00:00');

        $this->postJson("/api/v1/gamas/{$gamas->id}/updates", [
            'update_text' => 'Waktu depan',
            'started_at' => '2026-11-21T10:05:00+07:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('started_at');

        $this->postJson("/api/v1/gamas/{$gamas->id}/updates", [
            'update_text' => 'Sebelum gangguan',
            'started_at' => '2026-11-21T07:00:00+07:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('started_at');

        $this->postJson("/api/v1/gamas/{$gamas->id}/updates", [
            'update_text' => 'Update pertama',
            'started_at' => '2026-11-21T09:00:00+07:00',
        ])->assertCreated()->assertJsonPath('data.duration_minutes', 60);

        Carbon::setTestNow(Carbon::parse('2026-11-21 10:10:00'));
        $this->postJson("/api/v1/gamas/{$gamas->id}/updates", [
            'update_text' => 'Update tumpang tindih',
            'started_at' => '2026-11-21T09:30:00+07:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('started_at');

        $this->assertDatabaseCount('daily_gamas_updates', 1);
        $this->assertSame('On-Progress', $gamas->fresh()->status);
    }

    /** @param array<string, mixed> $extra */
    protected function makeGamas(User $user, string $createdAt, array $extra = []): DailyGamas
    {
        $gamas = DailyGamas::create(array_merge([
            'report_date' => today()->toDateString(),
            'gamas_kind' => 'odp',
            'location_label' => 'ODP Timeline',
            'status' => 'On-Progress',
            'created_by' => $user->id,
            'action' => 'Cek lapangan',
        ], $extra));
        DB::table('daily_gamas')->where('id', $gamas->id)->update([
            'report_date' => today()->toDateString(),
            'created_at' => $createdAt,
        ]);

        return $gamas->refresh();
    }
}
