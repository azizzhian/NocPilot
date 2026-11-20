<?php

namespace Tests\Feature;

use App\Models\DailyGamas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GamasUpdateTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertJsonPath('data.author_name', 'NOC Satu');

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
}
