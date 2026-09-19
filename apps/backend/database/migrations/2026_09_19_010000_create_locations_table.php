<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('odc_id')->nullable()->constrained('odcs')->nullOnDelete();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $seedNames = collect();

        if (Schema::hasTable('dismantles') && Schema::hasColumn('dismantles', 'location')) {
            $seedNames = $seedNames->merge(
                DB::table('dismantles')
                    ->whereNotNull('location')
                    ->where('location', '!=', '')
                    ->distinct()
                    ->pluck('location')
            );
        }

        if (Schema::hasTable('report_tickets') && Schema::hasColumn('report_tickets', 'location')) {
            $seedNames = $seedNames->merge(
                DB::table('report_tickets')
                    ->whereNotNull('location')
                    ->where('location', '!=', '')
                    ->distinct()
                    ->pluck('location')
            );
        }

        $now = now();
        $rows = $seedNames
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique(fn ($n) => mb_strtolower($n))
            ->values()
            ->map(fn ($name) => [
                'name' => $name,
                'odc_id' => null,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('locations')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
