<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_gamas', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('gamas_kind', 30)->default('odp');
            $table->string('location_label');
            $table->string('impact')->nullable();
            $table->string('odc_name')->nullable();
            $table->string('problem')->nullable();
            $table->text('action')->nullable();
            $table->date('start_problem')->nullable();
            $table->date('end_problem')->nullable();
            $table->string('status')->default('On-Progress');
            $table->unsignedTinyInteger('shift')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
            $table->index('report_date');
            $table->index('status');
        });

        // Pindahkan baris gamas lama dari daily_complaints ke daily_gamas,
        // lalu hapus dari daily_complaints supaya modul komplain benar-benar independen.
        if (Schema::hasTable('daily_complaints') && Schema::hasColumn('daily_complaints', 'complaint_type')) {
            $allowedStatuses = ['On-Progress', 'Clear'];

            DB::table('daily_complaints')
                ->where('complaint_type', 'gamas')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($allowedStatuses) {
                    foreach ($rows as $row) {
                        $status = (string) ($row->status ?? '');
                        if (! in_array($status, $allowedStatuses, true)) {
                            // Menunggu Konfirmasi (dsb.) tidak ada di modul gamas → kembali On-Progress.
                            $status = 'On-Progress';
                        }

                        $location = trim((string) ($row->location_label ?? ''));
                        if ($location === '') {
                            $location = trim((string) ($row->customer_name ?? ''));
                        }
                        if ($location === '') {
                            $location = 'Gamas #'.$row->id;
                        }

                        DB::table('daily_gamas')->insert([
                            'report_date' => $row->report_date,
                            'gamas_kind' => in_array($row->gamas_kind, ['odp', 'upstream', 'olt', 'other'], true)
                                ? $row->gamas_kind
                                : 'other',
                            'location_label' => $location,
                            'impact' => $row->impact,
                            'odc_name' => $row->odc_name,
                            'problem' => $row->problem,
                            'action' => $row->action,
                            'start_problem' => $row->start_problem,
                            'end_problem' => $row->end_problem,
                            'status' => $status,
                            'shift' => $row->shift,
                            'created_by' => $row->created_by,
                            'cleared_by' => $row->cleared_by,
                            'cleared_at' => $row->cleared_at,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                        ]);

                        DB::table('daily_complaints')->where('id', $row->id)->delete();
                    }
                });
        }
    }

    public function down(): void
    {
        // Kembalikan ke daily_complaints sebelum tabel dihapus (chunkById aman terhadap delete).
        DB::table('daily_gamas')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('daily_complaints')->insert([
                        'report_date' => $row->report_date,
                        'complaint_type' => 'gamas',
                        'customer_name' => $row->location_label,
                        'gamas_kind' => $row->gamas_kind,
                        'location_label' => $row->location_label,
                        'impact' => $row->impact,
                        'odc_name' => $row->odc_name,
                        'problem' => $row->problem,
                        'action' => $row->action,
                        'start_problem' => $row->start_problem,
                        'end_problem' => $row->end_problem,
                        'status' => $row->status,
                        'shift' => $row->shift,
                        'created_by' => $row->created_by,
                        'cleared_by' => $row->cleared_by,
                        'cleared_at' => $row->cleared_at,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);
                }
            });

        Schema::dropIfExists('daily_gamas');
    }
};
